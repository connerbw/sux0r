{capture name=header}

    {* RSS Feed *}
    <link rel="alternate" type="application/rss+xml" title="{$r->sitename} | {$r->text.blog}" href="{$r->makeUrl('/blog/rss', null, true)}" />

    {literal}
    <style type="text/css">
    #proselytizer { border-color: #ffffff; }
    </style>
    {/literal}


{/capture}{strip}
{$r->assign('header', $smarty.capture.header)}
{include file=$r->xhtml_header}{/strip}

<div id="proselytizer">

{* Header *}
<div id="header">
    <h1>{$r->text.header|lower}</h1>
    {insert name="userInfo"}
    {$r->navlist()}
</div>
<div class="clearboth"></div>

<div id="middle" class="tagcloud">
    {* Tagcloud *}
    {$r->tagcloud($r->tc)}
</div>

<div id="footer">
    {$r->copyright()}
</div>


</div>

{include file=$r->xhtml_footer}