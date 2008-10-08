{capture name=header}

    <script src="{$r->url}/includes/symbionts/scriptaculous/lib/prototype.js" type="text/javascript"></script>

    {literal}
    <script type="text/javascript">
    // <![CDATA[
    // Toggle subscription to a feed
    function togglePrivate(log_id) {

        var url = '{/literal}{$r->url}/modules/admin/toggle.php{literal}';
        var pars = { id: log_id };

        new Ajax.Request(url, {
                method: 'post',
                parameters: pars,
                onSuccess: function(transport) {
                    // Toggle images
                    var myImage = transport.responseText.strip();
                    var myClass = 'img.private' + log_id;
                    var res = $$(myClass);
                    for (i = 0; i < res.length; i++) {
                        res[i].src = '{/literal}{$r->url}/media/{$r->partition}/assets/{literal}' + myImage;
                    }
                },
                onFailure: function(transport){
                    if (transport.responseText.strip())
                        alert(transport.responseText);
                }
        });
    }
    // ]]>
    </script>
    {/literal}

{/capture}{strip}
{$r->assign('header', $smarty.capture.header)}
{include file=$r->xhtml_header}{/strip}

<div id="proselytizer">

{* Header *}
<div id="header">
    {insert name="userInfo"}
    <div class='clearboth'></div>
</div>

    <div id="middle">

    <fieldset>
    <legend>{$r->text.log}{if $nickname}: {$nickname}{/if}</legend>

    <table class="adminTable">
    <thead>
        <tr>
            <td>{$r->text.nickname|lower}</td>
            <td>{$r->text.log|lower}</td>
            <td {if $sort == 'ts'}class="selected"{/if}><a href="{$ts_sort_url|escape:'html'}">{$r->text.timestamp|lower}</a></td>
            <td>{$r->text.private|lower}</td>
        </tr>
    </thead>
    <tbody>

    {foreach from=$r->ulog item=foo}

        <tr style="background-color:{cycle values="#ffffff,#eeeeee"}">
            <td style="text-align:left;"><a href="{$r->makeUrl('/user/profile')}/{$foo.nickname}">{$foo.nickname}</a></td>
            <td style="text-align:left;">{$foo.body_html}</td>
            <td>{$foo.ts}</td>
            <td>
                <img
                src="{$r->url}/media/{$r->partition}/assets/{if $foo.private}lock1.gif{else}lock2.gif{/if}"
                onclick="togglePrivate('{$foo.id}');"
                style='cursor: pointer;'
                class='private{$foo.id}'
                alt="" />
            </td>
        </tr>

    {/foreach}

    </tbody>
    </table>

    {$r->text.pager}

    </fieldset>

        <p><a href="{$r->makeUrl('/admin')}">{$r->text.back} &raquo;</a></p>

    </div>

</div>

{include file=$r->xhtml_footer}