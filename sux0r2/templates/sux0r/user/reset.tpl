{capture name=header}

    <style type="text/css">
    label {
        float: left;
        width: 160px;
        margin-right: 0.5em;
        text-align: right;
    }
    </style>

{/capture}{strip}
{$r->assign('header', $smarty.capture.header)}
{include file=$r->xhtml_header}{/strip}

<div id="proselytizer">

{* Header *}
<div id="header">
    {insert name="userInfo"}
</div>
<div class="clearboth"></div>

{* Content *}
<div id="middle">

<fieldset>
<legend>{$r->gtext.reset}</legend>

<form action="{$r->text.form_url}" name="{$form_name}" method="post" accept-charset="utf-8">
<input type="hidden" name="token" value="{$token}" />

{if $validate.$form_name.is_error !== false}
<p class="errorWarning">{$r->gtext.form_error} :</p>
{elseif $r->detectPOST()}
<p class="errorWarning">{$r->gtext.form_problem} :</p>
{/if}

<p>
<label>&nbsp;</label>
<strong>{$r->gtext.reset_2}</strong>
</p>

<p>
{strip}
    {capture name=error}
    {validate id="user" message=$r->gtext.form_error_16}
    {validate id="user2" message=$r->gtext.form_error_17}
    {/capture}
{/strip}

<label {if $smarty.capture.error}class="error"{/if}>{$r->gtext.nickname_or_email} :</label>
<input type="text" name="user" value="{$user}" />
{$smarty.capture.error}
</p>
<div class="clearboth"></div>

<p>
{strip}
    {capture name=error}
    {validate id="captcha" message=$r->gtext.form_error_11}
    {/capture}
{/strip}
<label {if $smarty.capture.error}class="error"{/if} >{$r->gtext.captcha} :</label>
<input type="text" name="captcha" />
{$smarty.capture.error}
</p>

<p>
<label>&nbsp;</label>
<img src="{$r->url}/modules/captcha/ajax.getImage.php?sid={$r->uniqueId()}" alt="Captcha" border="0" />
<a href="{$r->url}/modules/captcha/ajax.getSound.php"><img src="{$r->url}/includes/symbionts/securimage/images/audio_icon.png" alt="Audio Version"  border="0" /></a>
</p>
<div class="clearboth"></div>

<p>
<input type="button" class="button" value="{$r->gtext.cancel}" onclick="document.location='{$r->text.back_url}';" />
<input type="submit" value="{$r->gtext.submit}" class="button" />
</p>

</form>

</fieldset>

</div>

</div>

{include file=$r->xhtml_footer}