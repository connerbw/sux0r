{capture name=header}

    <style type="text/css">
    label {ldelim}
        float: left;
        width: 140px;
        margin-right: 0.5em;
        padding-top: 0.2em;
        text-align: right;
    {rdelim}
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
<legend>{if $r->bool.edit}{$r->text.editing} : {$nickname}{else}{$r->text.reg}{/if}</legend>

<form action="{$r->text.form_url}" name="default" method="post" accept-charset="utf-8">
<input type="hidden" name="token" value="{$token}" />

{if $validate.default.is_error !== false}
<p class="errorWarning">{$r->text.form_error} :</p>
{elseif $r->detectPOST()}
<p class="errorWarning">{$r->text.form_problem} :</p>
{/if}

<p>
{strip}
    {capture name=error}
    {validate id="nickname" message=$r->text.form_error_1}
    {validate id="nickname2" message=$r->text.form_error_2}
    {validate id="nickname3" message=$r->text.form_error_3}
    {validate id="nickname4" message=$r->text.form_error_13}
    {/capture}
{/strip}

<label {if $smarty.capture.error}class="error"{/if} >* {$r->text.nickname} :</label>
<input type="text" name="nickname" value="{$nickname}" {if $r->bool.edit}readonly="readonly"{/if} />
{$smarty.capture.error}

{if $r->bool.edit}
<input type="hidden" name="integrity" value="{$r->integrityHash($nickname)}" />
{validate id="integrity" message=$r->text.form_error_12}
{/if}

</p>

<p>
{strip}
    {capture name=error}
    {validate id="email" message=$r->text.form_error_4}
    {validate id="email2" message=$r->text.form_error_5}
    {/capture}
{/strip}

<label {if $smarty.capture.error}class="error"{/if} >* {$r->text.email} :</label>
<input type="text" name="email" value="{$email}" />
{$smarty.capture.error}
</p>


{if $r->bool.openid}

    <p>
    <label>{$r->text.openid} :</label> {$r->text.openid_url}
    </p>

{else}

    <p>
    {strip}
        {capture name=error}
        {validate id="password" message=$r->text.form_error_6}
        {validate id="password2" message=$r->text.form_error_7}
        {/capture}
    {/strip}

    <label {if $smarty.capture.error}class="error"{/if}>* {$r->text.password} :</label>
    <input type="password" name="password" value="{$password}" />
    {$smarty.capture.error}
    </p>

    <p>
    <label>{$r->text.password_verify} :</label>
    <input type="password" name="password_verify" value="{$password_verify}" />
    </p>

{/if}


<p>
<label>{$r->text.given_name} :</label>
<input type="text" name="given_name" value="{$given_name}" />
</p>

<p>
<label>{$r->text.family_name} :</label>
<input type="text" name="family_name" value="{$family_name}" />
</p>

<p>
<label>{$r->text.street_address} :</label>
<input type="text" name="street_address" value="{$street_address}" />
</p>

<p>
<label>{$r->text.locality} :</label>
<input type="text" name="locality" value="{$locality}" />
</p>

<p>
<label>{$r->text.region} :</label>
<input type="text" name="region" value="{$region}" />
</p>

<p>
<label>{$r->text.postcode} :</label>
<input type="text" name="postcode" value="{$postcode}" />
</p>

<p>
<label>{$r->text.country} :</label>
{html_options name='country' options=$r->getCountries() selected=$country}
</p>

<p>
<label>{$r->text.tel} :</label>
<input type="text" name="tel" value="{$tel}" />
</p>

<p>
<label>{$r->text.url} :</label>
<input type="text" name="url" value="{$url}" />
</p>

<p>
<label>{$r->text.dob} :</label>
<span class="htmlSelectDate">
{html_select_date time="$Date_Year-$Date_Month-$Date_Day" field_order='YMD' start_year='-100' reverse_years=true year_empty='---' month_empty='---' day_empty='---'}
</span>
</p>


<p>
<label>{$r->text.gender} :</label>
<span class="htmlRadios">
{html_radios name='gender' options=$r->getGenders() selected=$gender}
</span>
</p>


<p>
<label>{$r->text.language} :</label>
{html_options name='language' options=$r->getLanguages() selected=$language}
</p>

<p>
<label>{$r->text.timezone} :</label>
{html_options name='timezone' options=$r->getTimezones() selected=$timezone}
</p>

{if !$r->bool.edit}
    <p>
    {strip}
        {capture name=error}
        {validate id="captcha" message=$r->text.form_error_11}
        {/capture}
    {/strip}
    <label {if $smarty.capture.error}class="error"{/if} >* {$r->text.captcha} :</label>
    <img src="{$r->url}/modules/captcha/getImage.php" alt="Captcha" style="margin-bottom: 0.5em;" />
    <br />
    <label>&nbsp;</label>
    <input type="text" name="captcha" class="captcha"/>
    {$smarty.capture.error}
    </p>
{/if}

<p>
<label>&nbsp;</label>
<input type="button" class="button" value="{$r->text.cancel}" onclick="document.location='{$r->text.back_url}';" />
<input type="submit" class="button" value="{$r->text.submit}" />
</p>

</form>

</fieldset>

</div>

</div>

{include file=$r->xhtml_footer}