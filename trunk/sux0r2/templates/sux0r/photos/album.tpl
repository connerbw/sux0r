{include file=$r->xhtml_header}

<table id="proselytizer">
	<tr>
		<td colspan="2" style="vertical-align:top;">
			<div id="header">

                <h1>sux0r - it sux0rs up all the web</h1>
                {insert name="userInfo"}
                {$r->navlist()}

			</div>
            <div class="clearboth"></div>
		</td>
	</tr>
	<tr>
		<td style="vertical-align:top;">
			<div id="leftside">

            TODO

			</div>
		</td>
		<td style="vertical-align:top;">
			<div id="rightside">


                <div class="widget">
                    <h2><a href="{$r->text.album_url}">{$r->text.album}</a></h2>

                    <div style="padding-left: 30px;">

                    {if $r->pho}
                    {foreach from=$r->pho item=foo name=bar}

                       {strip}
                       <a href="{$r->makeUrl('/photos/view')}/{$foo.id}">
                       <img class="thumbnail" src="{$r->url}/data/photos/{$foo.image}" alt="" width="{#thumbnailWidth#}" height="{#thumbnailHeight#}" >
                       </a>
                       {/strip}

                    {/foreach}
                    {/if}

                    <div class="clearboth"></div>

                    </div>

                    <p>{$r->text.pager}</p>


                    <div class="clearboth"></div>
                    <b class="bb"><b></b></b>

                </div>



			</div>
		</td>
	</tr>
	<tr>
		<td colspan="2" style="vertical-align:bottom;">
			<div id="footer">
			Footer
			</div>
		</td>
	</tr>
</table>


{include file=$r->xhtml_footer}