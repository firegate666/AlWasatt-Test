<?php

/**
 * game actions.
 *
 * @package    dice
 * @subpackage game
 * @author     Your name here
 * @version    SVN: $Id: actions.class.php 23810 2009-11-12 11:07:44Z Kris.Wallsmith $
 */
class gameActions
    extends sfActions
{

    const GAME_PHASE_ENROLL_PLAYERS = 1;
    const GAME_PHASE_PLAY = 2;
    const GAME_PHASE_OVER = 3;
    
    const NO_DICE_ROLLED = '* * * * *';
    
    private $_scoresMap = array(
        3 => array(1 => 1000, 2 => 200, 3 => 300, 4 => 400, 5 => 500, 6 => 600),
        1 => array(1 => 100, 5 => 50)
    );

    /**
     * Executes index action
     *
     * @param sfRequest $request A request object
     */
    public function executeIndex(sfWebRequest $request)
    {
        if ($this->getRequestParameter('reset') !== NULL)
            session_destroy();
        
        $sid = session_id();
        if (empty ($sid))
            session_start();
        unset ($sid);

        switch ($this->getGamePhase())
        {
            case self::GAME_PHASE_PLAY:
                $this->play();
                break;
            
            case self::GAME_PHASE_OVER:
                $this->gameover();
                break;
            
            default:
                $this->enrollPlayers();
                break;
        }
        
        return $this->getPhaseView();
    }
    
    private function getGamePhase()
    {
        if (isset ($_SESSION['gamePhase']))
            $phase = (int) $_SESSION['gamePhase'];
        else
            $phase = self::GAME_PHASE_ENROLL_PLAYERS;

        return $phase;
    }
    
    private function getPhaseView()
    {
        switch ($this->getGamePhase())
        {
            case self::GAME_PHASE_PLAY:
                return 'Play';
            
            case self::GAME_PHASE_OVER:
                return 'Over';
                
            default:
                return 'Enroll';
        }
    }
    
    private function enrollPlayers()
    {
        if ($this->getRequestParameter('add') !== NULL)
        {
            $this->addPlayer( $this->getRequestParameter('playerName') );
        }
        else if ($this->getRequestParameter('roll') !== NULL)
        {
            $playerIndex = (int) $this->getRequestParameter('roll');
            $this->setCurrentPlayerIndex( $playerIndex )
                ->executePlayerTurn()
                ;
        }
        else if ($this->getRequestParameter('enroll') !== NULL)
        {
            $playerIndex = (int) $this->getRequestParameter('enroll');
            $this->setCurrentPlayerIndex( $playerIndex )
                ->persistScore()
                ->markCurrentPlayerHasEnrolled()
                ;
        }
        else if ($this->canGameBegin() && $this->getRequestParameter('begin') !== NULL)
        {
            return $this->discardNonEnrolledPlayers()
                ->switchGamePhase()
                ->setCurrentPlayerIndex(0)
                ->play()
                ;
        }
        
        $this->players = $this->getPlayers();
        $this->canGameBegin = $this->canGameBegin();
        
        return $this;
    }

    private function addPlayer($name)
    {
        $players = $this->getPlayers();

        $players[ count($players) ] = array(
            'name' => $name
            , 'enrolled' => FALSE
            , 'canEnroll' => FALSE
            , 'totalScore' => 0
            , 'lastRoll' => self::NO_DICE_ROLLED
            , 'nextRollDices' => 5
            , 'turnScore' => 0
            , 'lastRollScore' => 0
            , 'turnVoid' => FALSE
            );
        
        $this->setPlayers($players);
        
        return $this;
    }
    
    private function markCurrentPlayerHasEnrolled()
    {
        $player = $this->getCurrentPlayer();
        $player['enrolled'] = TRUE;
        $player['canEnroll'] = FALSE;
        
        return $this->setCurrentPlayer($player);
    }

    private function canGameBegin()
    {
        $players = $this->getPlayers();
        
        $enrolled = 0;
        foreach ($players as $player)
        {
            if (! empty ($player['enrolled']))
            {
                $enrolled++;
                if ($enrolled > 1) 
                    break;
            }
        }
        
        return ($enrolled == 2);
    }
    
    private function discardNonEnrolledPlayers()
    {
        $players = $this->getPlayers();
        $newPlayers = array();
        
        foreach ($players as $player)
        {
            if (! empty ($player['enrolled']))
            {
                $newPlayers[] = $player;
            }
        }
        
        return $this->setPlayers($newPlayers);
    }
    
    private function switchGamePhase()
    {
        switch ($this->getGamePhase())
        {
            case self::GAME_PHASE_ENROLL_PLAYERS:
                $_SESSION['gamePhase'] = self::GAME_PHASE_PLAY;
                break;
            
            case self::GAME_PHASE_PLAY:
                $_SESSION['gamePhase'] = self::GAME_PHASE_OVER;
                break;
        }

        return $this;
    }
    
    private function play()
    {
        if ($this->getRequestParameter('pass') !== NULL)
        {
            $this->persistScore();
            
            if ($this->canInitiateFinalRound())
                $this->beginFinalRound()
                    ->markCurrentPlayerHasPlayedFinalTurn();
            elseif ($this->getIsFinalRound())
                $this->markCurrentPlayerHasPlayedFinalTurn();
            
            $this->switchToNextPlayer();
        }
        elseif ($this->getRequestParameter('roll') !== NULL)
        {
            $switchPlayer = TRUE;
            
            if ($this->executePlayerTurn())
            {
                if ($this->getIsFinalRound())
                {
                    $this->persistScore()
                        ->markCurrentPlayerHasPlayedFinalTurn()
                        ;
                }
                else
                    $switchPlayer = FALSE;
            }

            // will only be FALSE when all users are blacklisted after playing their final turns
            if ($switchPlayer && $this->switchToNextPlayer() === FALSE)
                // lets find out who is the winner
                $this->switchGamePhase()
                    ->gameover()
                    ;
        }
        
        // assigning view data
        $currentPlayer = $this->getCurrentPlayer();
        
        $this->playerName = $currentPlayer['name'];
        $this->turnOfPlayer = $this->getCurrentPlayerIndex();
        $this->players = $this->getPlayers();
        $this->isFinalRound = $this->getIsFinalRound();

        return $this;
    }
    
    private function getPlayers()
    {
        if (empty($_SESSION['players'])) 
            $players = array();
        else 
            $players = (array) $_SESSION['players'];

        return $players;
    }
    
    private function setPlayers(array $players)
    {
        $_SESSION['players'] = $players;
        return $this;
    }

    private function switchToNextPlayer()
    {
        $turnOfPlayer = $initialPlayer = $this->getCurrentPlayerIndex();
        $players = $this->getPlayers();
        
        $blacklist = array();
        if ($this->getIsFinalRound())
            $blacklist = $this->getPlayersPlayedFinalRound();
        
        do
        {
            $turnOfPlayer++;
            if (count($players) == $turnOfPlayer)
                $turnOfPlayer = 0;
            
            if ($turnOfPlayer == $initialPlayer)
                return FALSE;
            
        } while(in_array($turnOfPlayer, $blacklist));
        
        return $this->setCurrentPlayerIndex($turnOfPlayer);
    }
    
    private function getCurrentPlayer()
    {
        $players = $this->getPlayers();
        $turnOfPlayer = $this->getCurrentPlayerIndex();
        
        return $players[ $turnOfPlayer ];
    }
    
    private function setCurrentPlayer($updatedProfile)
    {
        $players = $this->getPlayers();
        $turnOfPlayer = $this->getCurrentPlayerIndex();
        
        $players[ $turnOfPlayer ] = $updatedProfile;
        
        $this->setPlayers($players);
        
        return $this;
    }
    
    private function getCurrentPlayerIndex()
    {
        if (isset ($_SESSION['turnOfPlayer']))
            $turnOfPlayer = (int) $_SESSION['turnOfPlayer'];
        else
            $turnOfPlayer = 0;
        
        return $turnOfPlayer;
    }
    
    private function setCurrentPlayerIndex($index)
    {
        $_SESSION['turnOfPlayer'] = $index;
        return $this;
    }

    private function persistScore()
    {
        $player = $this->getCurrentPlayer();
        
        $player['totalScore'] += $player['turnScore'];
        
        if ($player['enrolled'] && $player['totalScore'] >= 3000)
        {
            $this->beginFinalRound();
        }
        
        return $this->resetTurn($player);
    }
    
    private function resetTurn(array $player = NULL)
    {
        if (! is_array($player))
            $player = $this->getCurrentPlayer();
        
//        $player['lastRoll'] = self::NO_DICE_ROLLED;
        $player['turnScore'] = 0;
        $player['nextRollDices'] = 5;
        $player['canEnroll'] = FALSE;
        
        return $this->setCurrentPlayer($player);
    }
    
    private function canInitiateFinalRound()
    {
        if ($this->getIsFinalRound())
            return FALSE;
        
        $player = $this->getCurrentPlayer();
        return ($player['totalScore'] >= 3000);
    }
    
    private function beginFinalRound()
    {
        $_SESSION['isFinalRound'] = TRUE;
        return $this;
    }
    
    private function getIsFinalRound()
    {
        return (! empty ($_SESSION['isFinalRound']));
    }
    
    private function markCurrentPlayerHasPlayedFinalTurn()
    {
        $players = $this->getPlayersPlayedFinalRound();
        
        $players[] = $this->getCurrentPlayerIndex();
        
        $_SESSION['finalRoundPlayed'] = $players;
        
        return $this;
    }
    
    private function getPlayersPlayedFinalRound()
    {
        if (isset ($_SESSION['finalRoundPlayed']))
            $players = (array) $_SESSION['finalRoundPlayed'];
        else
            $players = array();

        return $players;
    }
    
    private function executePlayerTurn()
    {
        $player = $this->getCurrentPlayer();
        
        $player['lastRoll'] = $this->rollDices($player['nextRollDices']);

        $result = $this->calculateScore($player['lastRoll']);

        $player['lastRollScore'] = $result['score'];

        if ($result['score']) 
        {
            $player['turnScore'] += $result['score'];
            $player['nextRollDices'] = ($result['nonScoringDices'] == 0 ? 5 : $result['nonScoringDices']);
            $player['turnVoid'] = FALSE;
            
            if (empty ($player['enrolled']))
            {
                $player['canEnroll'] = ($player['turnScore'] >= 300);
            }
            
            $this->setCurrentPlayer($player);
        }
        else
        {
            $player['turnVoid'] = TRUE;
            $this->resetTurn($player);
        }
        
        return ! $this->hasBricked($player);
    }
    
    private function hasBricked(array $player = NULL)
    {
        if (empty ($player))
            $player = $this->getCurrentPlayer();
        
        return $player['turnVoid'];
    }
    
    private function rollDice()
    {
        return mt_rand(1, 6);
    }

    private function rollDices($howMany)
    {
        $rolls = array();

        while ($howMany > 0) {
            $rolls[] = $this->rollDice();
            $howMany--;
        }

        return implode(' ', $rolls);
    }

    private function calculateScore($rolls)
    {
        // stripping off spaces
        $rolls = str_replace(' ', '', $rolls);

        // see: http://www.php.net/manual/en/function.count-chars.php
        $counts = count_chars($rolls, 1);

        // accumulated score
        $score = 0;

        // reverse sorting scores map for higher counts to raise up
        krsort($this->_scoresMap);

        // counts yield points
        $scoringCounts = array_keys($this->_scoresMap);

        // count of non-scoring dices
        $nonScoringDices = strlen($rolls);

        // looping for each found char and its count
        foreach ($counts as $chr => $count) {
            foreach ($scoringCounts as $scoreMax) {
                while ($count >= $scoreMax) {
                    // converting from ASCII byte value to real number
                    $number = chr($chr);

                    if (array_key_exists($number, $this->_scoresMap[$scoreMax])) {
                        $nonScoringDices -= $scoreMax;
                        $score += $this->_scoresMap[$scoreMax][$number];
                    }

                    $count -= $scoreMax;
                }
            }
        }

        return array('score' => $score, 'nonScoringDices' => $nonScoringDices);
    }
    
    private function gameover()
    {
        $players = $this->getPlayers();
        $winnerIndex = $this->computeWinner();
        $winner = $players[ $winnerIndex ];
        
        $this->winner = $winner['name'];
        $this->winnerIndex = $winnerIndex;
        $this->players = $players;

        return $this;
    }
    
    private function computeWinner()
    {
        $scores = array();
        foreach ($this->getPlayers() as $player)
        {
            $scores[] = $player['totalScore'];
        }

        arsort($scores);
        reset($scores);

        return key($scores);
    }

}
