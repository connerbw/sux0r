{include file=$r->xhtml_header}

<table id="proselytizer" >
	<tr>
		<td colspan="2" style="vertical-align:top;">
			<div id="header">

                <h1>sux0r - it sux0rs up all the web</h1>
                {insert name="userInfo"}
                {$r->navlist()}

			</div>
		</td>
	</tr>
	<tr>
        <td style="vertical-align:top;">
			<div id="leftside">

            Lorem ipsum dolor sit amet, consec tetuer Lorem ipsum dolor sit amet

            <!-- menu -->
            <div class='menucontainer'>
            <ul class='menulist'>
            <li><a href='#' class='selected'>Item One</a></li>
            <li><a href='cake'>Item Two</a></li>
            <li><a href='#'>Item Three</a></li>
            <li><a href='http://www.trotch.com'>Item Four</a></li>
            </ul>
            </div>
            <div class='clearboth'></div>

            <p>Thumbnails?</p>

            <div style="margin-left: 10px;">

            <div class="thumbnail">
            <img src="{$r->url}/media/{$r->partition}/pavatars/bunker.jpg" alt="" width="60" height="60">
            </div>

            <div class="thumbnail">
            <img src="{$r->url}/media/{$r->partition}/pavatars/bunker.jpg" alt="" width="60" height="60">
            </div>

            <div class="thumbnail">
            <img src="{$r->url}/media/{$r->partition}/pavatars/bunker.jpg" alt="" width="60" height="60">
            </div>

            <br class="clearboth" />

            </div>


			</div>
		</td>
		<td style="vertical-align:top;">
			<div id="rightside">

                <div class="widget">
                    <h2>Title</h2>
                    <div class="floatright">
                        <img src="{$r->url}/media/{$r->partition}/pavatars/bunker.jpg" alt="" width="80" height="80"><br>
                        Caption
                    </div>
                    <p>Lorem ipsum dolor sit amet, consectetuer...</p>
                    <div class="clearboth"></div>
                    <b class="bb"><b></b></b>
                </div>

                <div class="widget">
                    <h2>Lorem ipsum</h2>
                    <div class="floatright">
                    <img src="{$r->url}/media/{$r->partition}/pavatars/bunker.jpg" alt="" width="80" height="80"><br>
                    Caption
                    </div>
                    <p>Lorem ipsum dolor sit amet, consectetuer Lorem ipsum dolor sit amet, consectetuer Lorem ipsum dolor sit amet, consectetuer
                    Lorem ipsum dolor sit amet, consec tetuer Lorem ipsum dolor sit amet, consectetuer Lorem ipsum dolor sit amet, consectetuer
                    Lorem ipsum dolor sit amet, consectetuer Lorem ipsum dolor sit amet, consect etuer Lorem ipsum dolor sit amet, consectetuer
                    Lorem ipsum dolor sit amet, consec tetuer Lorem ipsum dolor sit amet, consectetuer Lorem ipsum dolor sit amet, consectetuer
                    </p>
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