<?php use_helper('DiceGame') ?>
<h3>Game Over</h3>
<p>The winner is: <strong><?= $winner ?></strong></p>

<h4>Score card</h4>
<?= render_final_scorecard($players, $winnerIndex) ?>

<?= reset_button() ?>