{include file=$r->xhtml_header}

<div id="proselytizer"><div id="middle">

{$r->text.accept_mode}
<br />
<b>{$r->text.unaccepted_url}</b>
<br /><br />
{$r->text.continue}
<br />
<a href="{$r->text.always_url}">{$r->text.always}</a> | <a href="{$r->text.yes_url}">{$r->text.yes}</a> | <a href="{$r->text.no_url}">{$r->text.no}</a>

</div></div>

{include file=$r->xhtml_footer}