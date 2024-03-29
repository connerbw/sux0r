<?php

// Ajax
// Echo the content of a bayesian document

if (isset($_POST['id']) && filter_var($_POST['id'], FILTER_VALIDATE_INT)) {

    require_once(__DIR__ . '/../../config.php');
    require_once(__DIR__ . '/../../initialize.php');

    $nb = new suxNaiveBayesian();
    $doc = $nb->getDocument($_POST['id']);
    if ($doc) {

        $text = suxFunct::gtext('bayes');

        $tmp = null;
        $link = new suxLink();
        foreach ($link->getLinkTables('bayes_documents') as $table) {
            $links = $link->getLinks($table, 'bayes_documents', $_POST['id']);
            if($links && count($links)) {

                $table = str_replace('link__', '', (string) $table);
                $table = str_replace('bayes_documents', '', (string) $table);
                $table = str_replace('__', '', (string) $table);

                $tmp .= "[ {$text['to']} {$table}_id -&gt; ";
                foreach ($links as $val) $tmp .= " $val,";
                $tmp = rtrim($tmp, ', ');
                $tmp .= ' ]';
            }
        }

        echo '<em>bayes_document_id: ' , $_POST['id'] , '</em><br />';

        if ($tmp) {
            echo "<em><strong>{$text['is_linked']}</strong></em> ";
            echo $tmp;
        }
        else {
            echo "<em><strong>{$text['is_not_linked']}</strong></em> ";
        }
        echo "\n<div style='border-bottom: 1px solid #ccc; margin-top:4px;'></div>\n";
        echo "<pre>";
        echo $doc['body'];
        echo "</pre>\n";
    }

}

