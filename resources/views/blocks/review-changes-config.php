<?php use Fisharebest\Webtrees\Bootstrap4; ?>
<?php use Fisharebest\Webtrees\Functions\FunctionsEdit; ?>
<?php use Fisharebest\Webtrees\I18N; ?>

<p>
	<?= I18N::translate('This block will show editors a list of records with pending changes that need to be reviewed by a moderator. It also generates daily emails to moderators whenever pending changes exist.') ?>
</p>

<fieldset class="form-group">
	<div class="row">
		<legend class="col-form-label col-sm-3">
			<?= /* I18N: Label for a configuration option */ I18N::translate('Send out reminder emails') ?>
		</legend>
		<div class="col-sm-9">
			<?= Bootstrap4::radioButtons('sendmail', FunctionsEdit::optionsNoYes(), $sendmail, true) ?>
		</div>
	</div>
</fieldset>

<div class="form-group row">
	<label class="col-sm-3 col-form-label" for="days">
		<?= I18N::translate('Reminder email frequency (days)') ?>
	</label>
	<div class="col-sm-9">
		<input class="form-control" type="text" name="days" id="days" value="<?= e($days) ?>">
	</div>
</div>
