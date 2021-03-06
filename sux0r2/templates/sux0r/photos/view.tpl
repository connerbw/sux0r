{capture name=header}

    {* RSS Feed *}
    <link rel="alternate" type="application/rss+xml" title="{$r->sitename} | {$r->text.photos}" href="{$r->makeUrl('/photos/rss', null, true)}" />

    <script type="text/javascript">
    // <![CDATA[
    // Set the maximum width of an image
    function maximumWidth(myId, maxW) {
        var pix = document.getElementById(myId).getElementsByTagName('img');
        for (i = 0; i < pix.length; i++) {
            w = pix[i].width;
            h = pix[i].height;
            if (w > maxW) {
                f = 1 - ((w - maxW) / w);
                pix[i].width = w * f;
                pix[i].height = h * f;
            }
        }
    }
    window.onload = function() {
        maximumWidth('suxPhoto', {#maxPhotoWidth#});
    }
    // ]]>
    </script>

{/capture}{strip}
{$r->assign('header', $smarty.capture.header)}
{include file=$r->xhtml_header}{/strip}

<table id="proselytizer">
    <tr>
        <td colspan="2" style="vertical-align:top;">
            <div id="header">

                <h1>{$r->gtext.header|lower}</h1>
                {insert name="userInfo"}
                {insert name="navlist"}

            </div>
            <div class="clearboth"></div>
        </td>
    </tr>
    <tr>
        <td style="vertical-align:top;">
            <div id="leftside">

            <div class="editLinks">
            <p>{$r->gtext.publisher}: <a href="{$r->makeURL('/user/profile')}/{$r->arr.album.nickname}">{$r->arr.album.nickname}</a></p>
            {insert name="editLinks" album_id=$r->arr.album.id br=true}
            </div>

            <br />
            {$r->arr.album.body_html}

            </div>
        </td>
        <td style="vertical-align:top;">
            <div id="rightside">


                <div class="widget">
                    <h2><a href="{$r->text.back_url}">{$r->arr.album.title}</a></h2>

                    <div class="prevNext" style="width:{#maxPhotoWidth#}px;">
                        {if $r->text.prev_id}<a href="{$r->makeUrl('photos/view')}/{$r->text.prev_id}" class="previous">&laquo; {$r->gtext.prev}</a>{/if}
                        {if $r->text.next_id}<a href="{$r->makeUrl('photos/view')}/{$r->text.next_id}" class="next">{$r->gtext.next} &raquo;</a>{/if}
                    </div>

                    <p id="suxPhoto">
                    {strip}{if $r->text.next_id}<a href="{$r->makeUrl('photos/view')}/{$r->text.next_id}" class="noBg">{else}<a href="{$r->text.back_url}" class="noBg">{/if}
                    {/strip}<img class="photo" src="{$r->url}/data/photos/{$r->arr.photos.image|escape:'url'}" alt="" /></a>
                    </p>

                    {if $r->arr.photos.description}
                    <div class="description" style="width:{#maxPhotoWidth#}px;">
                    {$r->arr.photos.description}
                    </div>
                    {/if}

                    <div class="clearboth"></div>
                    <b class="bb"><b></b></b>
                </div>



            </div>
        </td>
    </tr>
    <tr>
        <td colspan="2" style="vertical-align:bottom;">
            <div id="footer">
            {$r->copyright()}
            </div>
        </td>
    </tr>
</table>


{include file=$r->xhtml_footer}