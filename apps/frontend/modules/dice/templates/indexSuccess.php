<?= $form->renderFormTag('/dice/index') ?>
    <dl>
        <dt>
            <?php 
            $fTurns = $form['turns'];
            echo $fTurns->renderLabel();
            ?>
        </dt>
        <dd>
            <?php 
            echo $fTurns->render(); 
            if ($fTurns->hasError()):
                echo $fTurns->renderError();
            endif;
            ?>
        </dd>

        <dt>&nbsp;</dt>
        <dd>
            <button type="submit">Submit</button>
        </dd>
    </dl>
</form>

<br/>
<?= link_to1('Play dice game', '/game/index') ?>
