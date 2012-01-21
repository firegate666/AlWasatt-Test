<?php

function render_enrollment_form($existingPlayers = NULL, $canBeginGame = FALSE)
{
    $cards = array();
    
    if (isset ($existingPlayers) && $existingPlayers instanceof sfOutputEscaperArrayDecorator)
    {
        $existingPlayers->rewind();
        while ($existingPlayers->valid())
        {
            $player = $existingPlayers->current();
            $index = $existingPlayers->key();

            $existingPlayers->next();
            
            $messenger = NULL;
            $buttons = "<button type=\"submit\" name=\"roll\" value=\"$index\">Roll Dices</button>";
            
            if ($player['enrolled'])
            {
                $messenger = '<p class="success">Enrolled!</p>';
                $buttons = NULL;
            }
            elseif ($player['canEnroll'])
                $buttons .= "<button type=\"submit\" name=\"enroll\" value=\"$index\">Enroll</button>";
            
            $rec = array('player' => $player, 'buttons' => $buttons);
            if (isset ($messenger))
                $rec['messenger'] = $messenger;
                
            $cards[ $index ] = $rec;
        }
    }
    
    $enrollmentForm = <<<EndOfEnrollmentForm
<dl>
    <dt>
        <label for="playerName">Player Name:</label>
    </dt>
    <dd>
        <input type="text" name="playerName" />
    </dd>
</dl>
EndOfEnrollmentForm;
    
    $enrollmentFormCard = array(
        'isActive' => TRUE
        , 'header' => "<h3>Add New Player</h3>"
        , 'body' => $enrollmentForm
        , 'footer' => '<button type="submit" name="add">Add Player</button>'
    );

    
    $formPostfix = '<br/><button type="submit" name="begin" ';
    if ($canBeginGame)
        $formPostfix .= '>Play Game</button>';
    else
    {
        $formPostfix .= <<<EndOfFormPostfix
disabled="disabled">Play Game</button> * <i>Need at least two <strong>enrolled</strong> players to begin.</i>
EndOfFormPostfix;
    }
    
    
    $cards[] = $enrollmentFormCard;
    $cards['postfix'] = $formPostfix;
    
    return render_info_cards($cards);
}

function render_play_stage($players, $whoseTurn)
{
    $cards = array();
    
    if (isset ($players) && $players instanceof sfOutputEscaperArrayDecorator)
    {
        $players->rewind();
        while ($players->valid())
        {
            $player = $players->current();
            $index = $players->key();

            $players->next();
            
            $rec = array('player' => $player, 'buttons' => NULL);
            if ($index == $whoseTurn)
            {
                $rec['isActive'] = TRUE;
                
                $buttons = "<button type=\"submit\" name=\"roll\" value=\"$index\">Roll Dices</button>";
                $buttons .= "<button type=\"submit\" name=\"pass\" value=\"$index\">Pass</button>";
                $rec['buttons'] = $buttons;
            }
            
            $cards[ $index ] = $rec;
        }
    }
    
    return render_info_cards($cards);
}

function render_final_scorecard($players, $whoseTheWinner)
{
    $cards = array();
    
    if (isset ($players) && $players instanceof sfOutputEscaperArrayDecorator)
    {
        $players->rewind();
        while ($players->valid())
        {
            $player = $players->current();
            $index = $players->key();

            $players->next();
            
            $rec = array('player' => $player);
            if ($index == $whoseTheWinner)
            {
                $rec['isActive'] = TRUE;
            }
            
            $cards[ $index ] = $rec;
        }
    }
    
    return render_info_cards($cards);
}

function render_info_cards(array $data)
{
    if (!is_array($data))
        return '';
    
    $cards = array();

    foreach ($data as $key => $record)
    {
        $cardRec = NULL;
        
        if (is_array($record) && isset ($record['player']))
        {
            $player = $record['player'];
            
            $dbg = var_export($player,TRUE);
            
            $messenger = NULL;
            if (isset ($record['messenger']))
                $messenger = $record['messenger'];
            elseif ($player['turnVoid'])
                $messenger = '<p class="error">Turn Void!</p>';
            
            $buttons = NULL;
            if (isset ($record['buttons']))
                $buttons = $record['buttons'];
            
            $playerInfo = <<<EndOfPlayerInfo
<dl class="scorecard">
    <dt>Dices</dt>
    <dd>{$player['lastRoll']}</dd>
    
    <dt>Turn Score</dt>
    <dd>{$player['turnScore']}</dd>
    
    <dt>Next Roll Dices</dt>
    <dd>{$player['nextRollDices']}</dd>
    
    <dt>Total Score</dt>
    <dd class="total">{$player['totalScore']}</dd>
</dl>
EndOfPlayerInfo;

            $footer = <<<EndOfCardFooter
{$messenger}
{$buttons}
<div class="dbg">{$dbg}</div>
EndOfCardFooter;
            
            $cardRec = array(
                'header' => "<h3>{$player['name']}</h3>"
                , 'body' => $playerInfo
                , 'footer' => $footer
                , 'isActive' => ! empty ($record['isActive'])
            );
        }
        else
            $cardRec = $record;
        
        $cards[ $key ] = $cardRec;
    }
    
    return create_info_cards($cards);
}

function create_info_cards(array $data)
{
    $cardsMarkup = '';
    
    $markupPrefix = '';
    if (isset ($data['prefix']))
    {
        $markupPrefix = $data['prefix'];
        unset($data['prefix']);
    }
    
    $markupPostfix = '';
    if (isset ($data['postfix']))
    {
        $markupPostfix = $data['postfix'];
        unset($data['postfix']);
    }
    
    foreach ($data as $aCardData)
    {
        $cardsMarkup .= create_a_info_card($aCardData);
    }
    
    return <<<EndOfInfoCardsMarkup
<form method="post" action="">
    {$markupPrefix}
    <div class="info-cards-container">
        {$cardsMarkup}
    </div>
    {$markupPostfix}
</form>
EndOfInfoCardsMarkup;
}

function create_a_info_card(array $data)
{
    $additionalClasses = array();
    $isActiveCard = (! empty ($data['isActive']));
    
    if ($isActiveCard)
        $additionalClasses[] = 'active';
    
    $additionalClasses = implode(' ', $additionalClasses);
    
    $wrapperOpen = '';
    if (isset ($data['wrapperOpen']))
        $wrapperOpen = $data['wrapperOpen'];
    
    $wrapperClose = '';
    if (isset ($data['wrapperClose']))
        $wrapperClose = $data['wrapperClose'];
    
    return <<<EndOfInfoCardMarkup
<div class="info-card {$additionalClasses}">
    {$wrapperOpen}
    
    <div class="header">
        {$data['header']}
    </div>
    
    <div class="body">
        {$data['body']}
    </div>
    
    <div class="footer">
        {$data['footer']}
    </div>
    
    {$wrapperClose}
</div>
EndOfInfoCardMarkup;
}

function reset_button()
{
    return '<form method="post" action=""><button type="submit" name="reset">Reset</button></form>';
}