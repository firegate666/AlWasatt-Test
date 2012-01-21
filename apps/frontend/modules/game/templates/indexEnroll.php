<?php use_helper('DiceGame') ?>
<?= render_enrollment_form($players, $canGameBegin) ?>
<?= reset_button() ?>
<?= link_to1('See Demo 1', '/dice/index') ?>