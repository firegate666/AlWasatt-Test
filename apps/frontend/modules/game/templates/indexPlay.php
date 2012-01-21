<?php 
use_helper('DiceGame');

if ($isFinalRound): 
?>
<h1>FINAL ROUND</h1>
<?php endif; ?>

<p>Whose turn? <?= $playerName ?></p>
<?= render_play_stage($players, $turnOfPlayer) ?>

<?= reset_button() ?>
