<?php

/**
 * dice actions.
 *
 * @package    dice
 * @subpackage dice
 * @author     Waseem <wassimahmed@hotmail.com>
 * @version    SVN: $Id: actions.class.php 23810 2009-11-12 11:07:44Z Kris.Wallsmith $
 */
class diceActions
    extends sfActions
{
    /**
     * Array map of scores
     * Key = iterations, while 
     * Value = array represents:
     *      Key = dice value
     *      Value = score
     * 
     * @access private
     * @var array 
     */
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
        $form = $this->createForm();
        
        if ($request->isMethod('post'))
        {
            $form->bind($request->getPostParameters());
            if ($form->isValid())
            {
                $this->redirect('dice/result?turns=' . (int) $form->getValue('turns'));
            }
        }
        
        $this->form = $form;
        
        // render Success template
        return sfView::SUCCESS;
    }

    /**
     * Executes result action
     *
     * @param sfRequest $request A request object
     */
    public function executeResult(sfWebRequest $request)
    {
        /* start-of: test cases
        // test case #1
        $this->rolls = '51341';
        $this->score = $this->calculateScore( $this->rolls );

        // test case #2
        $this->rolls = '11131';
        $this->score = $this->calculateScore( $this->rolls );

        // test case #3
        $this->rolls = '24454';
        $this->score = $this->calculateScore( $this->rolls );

        // end-of: test cases
        */

        // --- to run test-cases, comment out code starting from this line till ... ---
        // Casting to integer
        $this->turns = (int) $this->getRequestParameter('turns');

        // generate random value by simulating dice rolling for number of turns
        $this->rolls = $this->rollDices($this->turns);

        // assign computed score value for view
        $this->score = $this->calculateScore($this->rolls);
        // --- comment out till here and don't forget to uncomment any one test-case at a time. ---

        // rendering Success template
        return sfView::SUCCESS;
    }
    
    
    /**
     * Creates a form
     * 
     * @return sfForm 
     */
    private function createForm()
    {
        $form = new sfForm();
        $form->setWidgets(array(
            'turns' => new sfWidgetFormInputText(array('label' => 'Number of turns:', 'default' => 5)),
        ));
        $form->setValidators(array(
            'turns' => new sfValidatorNumber(array('min' => 1)),
        ));
        
        return $form;
    }

    /**
     * Generates a random number between 1 to 6 simulating dice roll
     * 
     * @return int 
     */
    private function rollDice()
    {
        return mt_rand(1, 6);
    }

    /**
     * Rolls dice for given number of times
     * 
     * @param int $howMany Number of rolls
     * @return string Returns contatenated string
     */
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
        // see: http://www.php.net/manual/en/function.count-chars.php
        $counts = count_chars(str_replace(' ', '', $rolls), 1);

        // accumulated score
        $score = 0;

        // reverse sorting scores map for higher counts to raise up
        krsort($this->_scoresMap);

        // counts yield points
        $scoringCounts = array_keys($this->_scoresMap);

        // looping for each found char and its count
        foreach ($counts as $chr => $count) {
            
            // looping for every rewarding count
            foreach ($scoringCounts as $scoreMax) {
                
                // loop until character count is greater than max rewardable count
                while ($count >= $scoreMax) {
                    
                    // converting from ASCII byte value to real number
                    $number = chr($chr);

                    // can we award points for $number (if exist in map)
                    if (array_key_exists($number, $this->_scoresMap[$scoreMax]))
                        // accumulate points for rewardable number
                        $score += $this->_scoresMap[$scoreMax][$number];

                    // decrease count for next iteration
                    $count -= $scoreMax;
                }
            }
        }

        // returns final accumulated score
        return $score;
    }

}
