{capture name=header}

{* Latest version *}
{assign var='version' value='2.2.1'}

{/capture}{strip}
{$r->assign('header', $smarty.capture.header)}
{include file=$r->xhtml_header}{/strip}

<table id="proselytizer">
    <tr>
        <td colspan="2" style="vertical-align:top;">
            <div id="header">

                <h1>sux0r - it sux0rs up all the web</h1>
                {insert name="userInfo"}
                {insert name="navlist"}

            </div>
            <div class="clearboth"></div>
        </td>
    </tr>
    <tr>
        <td style="vertical-align:top;">
            <div id="leftside">

            <p>
            <span class="highlight">sux0r</span> is a blogging package, an RSS aggregator, a bookmark repository,
            and a photo publishing platform with a focus on Naive Bayesian categorization and probabilistic content.
            <a href="http://openid.net/">OpenID</a> enabled <em>(version 1.1);</em> as both a consumer and a provider.
            </p>

            <p>
            <a href="http://en.wikipedia.org/wiki/Naive_Bayes_classifier">Naive Bayesian categorization</a>
            is the ouija board of mathematics. Known for being good at filtering junk mail, the Naive Bayesian
            algorithm can categorize anything so long as there are coherent reference
            texts to work from. For example, categorizing documents in relation to a vector
            of political manifestos, or religious holy books, makes for a neat trick.
            More subjective magic 8-ball categories could be  "good vs. bad", risk assessment,
            insurance claim fraud, whatever you want.
            </p>

            <p>
            <span class="highlight">sux0r</span> allows users
            to maintain lists of Naive Bayesian categories. These lists
            can be shared with other users. This allows groups
            to share, train, and use <span class="highlight">sux0r</span> together.
            </p>

            <p>
            <span class="highlight">sux0r {$version}</span> is <a href="http://sourceforge.net/projects/sux0r/">open source</a> and is distributed under
            the <a href="http://www.fsf.org/licensing/licenses/gpl-3.0.html">GNU General Public License</a>.
            </p>

            <p style="text-align: center;">
            <a href="http://www.php.net/" class="noBg"><img src="{$r->url}/media/sux0r/assets/php5_logo.gif" alt="PHP5" border="0" class="flair" /></a>
            <a href="http://www.fsf.org/licensing/licenses/gpl-3.0.html" class="noBg"> <img src="{$r->url}/media/sux0r/assets/gplv3-88x31.png" alt="GPL" border="0" class="flair" /></a>
            <a href="http://sourceforge.net/projects/sux0r" class="noBg"><img src="http://sflogo.sourceforge.net/sflogo.php?group_id=131752&amp;type=11" width="120" height="30" border="0" alt="Get sux0r at SourceForge.net. Fast, secure and Free Open Source software downloads"  class="flair" /></a>
            </p>


            </div>
        </td>
        <td style="vertical-align:top;">
            <div id="rightside">

            {* Capture content *}

            {capture name='title' assign='title'}Latest Version: {$version}{/capture}
            {capture name='welcome' assign='welcome'}

            <p>
            Sux0r is multilingual. Translators welcome.
            Currently we have English (en), Chinese (zh), Dutch (nl), Italian (it), and German (de).
            </p>

            <p>
            Sux0r is skinable. Designers welcome. Help sux0r not suck so much in the beauty department.
            </p>

            <p>
            Still not sure what this is all about?
            <a href="http://bayesianfeedfilter.wordpress.com/2009/12/11/bayesff-final-post/">Read this blog post</a>
            by the superb BayesFF project.
            </p>

            {/capture}
            {capture name='img' assign='img'}{$r->myHttpServer()}{$r->url}/media/sux0r/assets/nullwhore.png{/capture}

            {* Render widget *}
            {$r->widget($title, $welcome, 'http://sourceforge.net/projects/sux0r/', $img, '', 'http://sux0r.trotch.com/user/profile/conner_bw')}

            <p id="sfLinks">
            Found a bug? <a href="http://sourceforge.net/tracker2/?atid=722155&amp;group_id=131752">Report it</a>.
            Need help? <a href="http://sourceforge.net/projects/sux0r/forums/forum/447216">Ask in the Support forum</a>.
            Got ideas to discuss? Post in the <a href="http://sourceforge.net/projects/sux0r/forums/forum/447217">Developers forum</a> or submit
            <a href="http://sourceforge.net/tracker2/?group_id=131752&amp;atid=722157">patches</a>.
            All that and more at the <a href="http://sourceforge.net/projects/sux0r/">sux0r SF.net project page</a>.
            </p>

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
