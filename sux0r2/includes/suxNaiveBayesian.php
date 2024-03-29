<?php

/**
* suxNaiveBayesian
*
* Forked from / Inspired by:
* Loic d'Anterroches: http://www.xhtml.net/scripts/PHPNaiveBayesianFilter
* Ken Williams: http://mathforum.org/~ken/bayes/bayes.html
*
* @author     Dac Chartrand <dac.chartrand@gmail.com>
* @license    http://www.fsf.org/licensing/licenses/gpl-3.0.html
*/

// User/link intergration is purposely left out of this class for reusability
// reasons. An extended version of this class, with user integration, is
// located in ./extensions/bayesUser.php

class suxNaiveBayesian {

    // Database suff
    protected $db;
    protected $inTransaction = false;
    protected $db_driver;
    // Tables
    protected $db_table_vec = 'bayes_vectors';
    protected $db_table_cat = 'bayes_categories';
    protected $db_table_doc = 'bayes_documents';
    protected $db_table_tok = 'bayes_tokens';
    protected $db_table_cache = 'bayes_cache';

    // Set to false when debugging
    public $use_cache = true;

    // If you change these, then you need to adjust your database columns
    private int $max_category_length = 64;
    private int $max_vector_length = 64;

    // Controlled destruction
    protected static $destroyed = false;


    /**
    * Constructor
    */
    function __construct() {

        $this->db = suxDB::get();
        $this->db_driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        set_exception_handler(array($this, 'exceptionHandler'));


    }


    /**
    * Destructor
    */
    function __destruct() {

        if (self::$destroyed == true) return; // Avoid cleaning the cache multiple times
        if (1 == random_int(1, 5)) $this->cleanCache(); // 1 in 5 chance
        self::$destroyed = true;

    }


    // ----------------------------------------------------------------------------
    // Accessors
    // ----------------------------------------------------------------------------

    /**
    * @return int
    */
    function getMaxVectorLength() {
        return $this->max_vector_length;
    }


    /**
    * @return int
    */
    function getMaxCategoryLength() {
        return $this->max_category_length;
    }


    // ----------------------------------------------------------------------------
    // Vectors
    // ----------------------------------------------------------------------------

    /**
    * @param string $vector vector
    * @return bool
    */
    function addVector($vector) {

        if (mb_strlen($vector) > $this->max_vector_length) return false;

        // Sanitize
        $vector = strip_tags($vector);

        $st = $this->db->prepare("INSERT INTO {$this->db_table_vec} (vector) VALUES (?) ");
        return $st->execute(array($vector));

    }


    /**
    * @param int $vector_id vector id
    * @return bool
    */
    function removeVector($vector_id) {

        if (!filter_var($vector_id, FILTER_VALIDATE_INT) || $vector_id < 1) return false;

        // Get the category ids for this vector
        $categories = array();
        $st = $this->db->prepare("SELECT id FROM {$this->db_table_cat} WHERE bayes_vectors_id = ? ");
        $st->execute(array($vector_id));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $categories[] = $row['id'];
        }

        $tid = suxDB::requestTransaction();
        $this->inTransaction = true;

        $count = 0;

        // As we are updating probabilities, we must clear the cache
        $this->deleteCache($vector_id);

        $st = $this->db->prepare("DELETE FROM {$this->db_table_vec} WHERE id = ? ");
        $st->execute(array($vector_id));
        $count += $st->rowCount();

        $st = $this->db->prepare("DELETE FROM {$this->db_table_cat} WHERE bayes_vectors_id = ? ");
        $st->execute(array($vector_id));
        $count += $st->rowCount();

        foreach ($categories as $val) {
            $st = $this->db->prepare("DELETE FROM {$this->db_table_doc} WHERE bayes_categories_id = ? ");
            $st->execute(array($val));
            $count += $st->rowCount();
        }

        foreach ($categories as $val) {
            $st = $this->db->prepare("DELETE FROM {$this->db_table_tok} WHERE bayes_categories_id = ? ");
            $st->execute(array($val));
            $count += $st->rowCount();
        }

        $this->updateProbabilities();

        suxDB::commitTransaction($tid);
        $this->inTransaction = false;

        return ($count > 0 ? true : false);
    }


    /**
    * @param string $vector_id vector id, must be unique
    * @return array|false
    */
    function getVector($vector_id) {

        $vector = [];
        $st = $this->db->prepare("SELECT * FROM {$this->db_table_vec} WHERE id = ? ");
        $st->execute(array($vector_id));

        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $vector['id'] = $row['id'];
            $vector['vector'] = $row['vector'];
            return $vector;
        }
        else return false;

    }


    /**
    * @return array key = id, values = array(keys = 'vector')
    */
    function getVectors() {

        $st = $this->db->query("SELECT * FROM {$this->db_table_vec} ORDER BY vector ASC ");

        $vectors = array();
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $vectors[$row['id']] = array(
                'vector' => $row['vector'],
                );
        }
        return $vectors;
    }


    /**
    * @param int $category_id category id
    * @return array key = id, values = array(keys = 'vector')
    */
    function getVectorByCategory($category_id) {

        if (!filter_var($category_id, FILTER_VALIDATE_INT) || $category_id < 1) return false;

        $query = "SELECT {$this->db_table_vec}.* FROM {$this->db_table_vec}
        INNER JOIN {$this->db_table_cat} ON {$this->db_table_vec}.id = {$this->db_table_cat}.bayes_vectors_id
        WHERE {$this->db_table_cat}.id = ? ";

        $st = $this->db->prepare($query);
        $st->execute(array($category_id));

        $vectors = array();
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            // There should only be one vector
            $vectors[$row['id']] = array(
                'vector' => $row['vector'],
            );
        }

        return $vectors;
    }


    /**
    * @param int $document_id category id
    * @return array key = id, values = array(keys = 'vector')
    */
    function getVectorsByDocument($document_id) {

        if (!filter_var($document_id, FILTER_VALIDATE_INT) || $document_id < 1) return false;

        $query = "SELECT {$this->db_table_vec}.* FROM {$this->db_table_vec}
        INNER JOIN {$this->db_table_cat} ON {$this->db_table_vec}.id = {$this->db_table_cat}.bayes_vectors_id
        INNER JOIN {$this->db_table_doc} ON {$this->db_table_cat}.id = {$this->db_table_doc}.bayes_categories_id
        WHERE {$this->db_table_doc}.id = ? ";

        $st = $this->db->prepare($query);
        $st->execute(array($document_id));

        $vectors = array();
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $vectors[$row['id']] = array(
                'vector' => $row['vector'],
                );
        }

        return $vectors;
    }


    // ----------------------------------------------------------------------------
    // Categories
    // ----------------------------------------------------------------------------


    /**
    * @param string $category category
    * @param int $vector_id vector id
    * @return bool
    */
    function addCategory($category, $vector_id) {

        if (mb_strlen($category) > $this->max_category_length) return false;
        if (!filter_var($vector_id, FILTER_VALIDATE_INT) || $vector_id < 1) return false;

        // Make sure vector exists
        $st = $this->db->prepare("SELECT COUNT(*) FROM {$this->db_table_vec} WHERE id = ? ");
        $st->execute(array($vector_id));
        if ($st->fetchColumn() < 1) return false;

        // Sanitize
        $category = strip_tags($category);

        try {
            $st = $this->db->prepare("INSERT INTO {$this->db_table_cat} (category, bayes_vectors_id) VALUES (?, ?) ");
            return $st->execute(array($category, $vector_id));
        }
        catch (Exception $e) {
            if ($st->errorCode() == 23000) return false; // SQLSTATE 23000: Constraint violation
            else throw ($e); // Hot potato
        }

    }


    /**
    * @param int $category_id category id
    * @return bool
    */
    function removeCategory($category_id) {

        if (!filter_var($category_id, FILTER_VALIDATE_INT) || $category_id < 1) return false;

        $tid = suxDB::requestTransaction();
        $this->inTransaction = true;

        // As we are updating probabilities, we must clear the cache
        $vector_id = $this->getVectorByCategory($category_id);
        $vector_id = array_keys($vector_id); // Get the key
        $vector_id = array_shift($vector_id);
        $this->deleteCache($vector_id);

        $count = 0;

        $st = $this->db->prepare("DELETE FROM {$this->db_table_cat} WHERE id = ? ");
        $st->execute(array($category_id));
        $count += $st->rowCount();

        $st = $this->db->prepare("DELETE FROM {$this->db_table_doc} WHERE bayes_categories_id = ? ");
        $st->execute(array($category_id));
        $count += $st->rowCount();

        $st = $this->db->prepare("DELETE FROM {$this->db_table_tok} WHERE bayes_categories_id = ? ");
        $st->execute(array($category_id));
        $count += $st->rowCount();

        $this->updateProbabilities();

        suxDB::commitTransaction($tid);
        $this->inTransaction = false;

        return ($count > 0 ? true : false);
    }


    /**
    * @param string $category_id category id, must be unique
    * @return array|false
    */
    function getCategory($category_id) {

        $category = [];
        $st = $this->db->prepare("SELECT * FROM {$this->db_table_cat} WHERE id = ? ");
        $st->execute(array($category_id));

        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $category['id'] = $row['id'];
            $category['category'] = $row['category'];
            $category['vector_id'] = $row['bayes_vectors_id'];
            $category['probability'] = $row['probability'];
            $category['token_count']  = $row['token_count'];
            return $category;
        }
        else return false;


    }


    /**
    * @return array key = id, values = array(keys = 'category', 'vector_id', 'probability', 'token_count')
    */
    function getCategories() {

        $st = $this->db->query("SELECT * FROM {$this->db_table_cat} ORDER BY category ASC ");

        $categories = array();
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $categories[$row['id']] = array(
                'category' => $row['category'],
                'vector_id' => $row['bayes_vectors_id'],
                'probability' => $row['probability'],
                'token_count'  => $row['token_count'],
                );
        }
        return $categories;
    }


    /**
    * @param int $vector_id vector id
    * @return array key = category, values = array(keys = 'category', 'vector_id', 'probability', 'token_count')
    */
    function getCategoriesByVector($vector_id) {

        if (!filter_var($vector_id, FILTER_VALIDATE_INT) || $vector_id < 1) return false;

        $categories = array();

        static $st = null; // Static as cache, to make categorize() faster
        if (!$st) $st = $this->db->prepare("SELECT * FROM {$this->db_table_cat} WHERE bayes_vectors_id = ? ORDER BY category ASC ");
        $st->execute(array($vector_id));

        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $categories[$row['id']] = array(
                'category' => $row['category'],
                'vector_id' => $row['bayes_vectors_id'],
                'probability' => $row['probability'],
                'token_count'  => $row['token_count'],
                );
        }

        return $categories;
    }


    /**
    * @param int $vector_id vector id
    * @return array key = category, values = array(keys = 'category', 'vector_id', 'probability', 'token_count')
    */
    function getCategoriesByDocument($document_id) {

        if (!filter_var($document_id, FILTER_VALIDATE_INT) || $document_id < 1) return false;

        $query = "SELECT
        {$this->db_table_cat}.* FROM {$this->db_table_cat}
        INNER JOIN {$this->db_table_doc} ON {$this->db_table_cat}.id = {$this->db_table_doc}.bayes_categories_id
        WHERE {$this->db_table_doc}.id = ? ORDER BY category ASC ";

        $st = $this->db->prepare($query);
        $st->execute(array($document_id));

        $categories = array();
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {

            $categories[$row['id']] = array(
                'category' => $row['category'],
                'vector_id' => $row['bayes_vectors_id'],
                'probability' => $row['probability'],
                'token_count'  => $row['token_count'],
                );
        }

        return $categories;
    }



    // ----------------------------------------------------------------------------
    // Documents
    // ----------------------------------------------------------------------------


    /**
    * @param string $category_id the category id in which the document should be
    * @param string $content content of the document
    * @return bool
    * @return int insert id
    */
    function trainDocument($content, $category_id) {

        if (!filter_var($category_id, FILTER_VALIDATE_INT) || $category_id < 1) return false;

        // Make sure category exists
        $st = $this->db->prepare("SELECT COUNT(*) FROM {$this->db_table_cat} WHERE id = ? ");
        $st->execute(array($category_id));
        if ($st->fetchColumn() < 1) return false;

        // Sanitize to UTF-8 plaintext
        $converter = new suxHtml2UTF8($content);
        $content = $converter->getText();

        $tid = suxDB::requestTransaction();
        $this->inTransaction = true;

        // As we are updating probabilities, we must clear the cache
        $vector_id = $this->getVectorByCategory($category_id);
        $vector_id = array_keys($vector_id); // Get the key
        $vector_id = array_shift($vector_id);
        $this->deleteCache($vector_id);

        $tokens = $this->parseTokens($content);
        foreach($tokens as $token => $count) {
            $this->updateToken($token, $count, $category_id);
        }
        $this->addDocument($category_id, $content);

        // MySQL InnoDB with transaction reports the last insert id as 0 after
        // commit, the real ids are only reported before committing.

        if ($this->db_driver == 'pgsql') $insert_id = $this->db->lastInsertId("{$this->db_table_doc}_id_seq"); // PgSql
        else $insert_id = $this->db->lastInsertId();

        $this->updateProbabilities();

        suxDB::commitTransaction($tid);
        $this->inTransaction = false;

        return $insert_id;

    }


    /**
    * @param string $document_id document id, must be unique
    * @return bool
    */
    function untrainDocument($document_id) {

        if (!filter_var($document_id, FILTER_VALIDATE_INT) || $document_id < 1) return false;

        $tid = suxDB::requestTransaction();
        $this->inTransaction = true;

        // As we are updating probabilities, we must clear the cache
        $vectors = $this->getVectorsByDocument($document_id);
        foreach($vectors as $key => $val) {
            $this->deleteCache($key);
        }

        $ref = $this->getDocument($document_id);
        if (is_countable($ref) ? count($ref) : 0) {

            // Checking against stopwords is a big performance hog and
            // they don't affect the results here, so not using them
            // speeds things up significantly, hence false

            $tokens = $this->parseTokens($ref['body'], false);

            foreach($tokens as $token => $count) {
                $this->removeToken($token, $count, $ref['category_id']);
            }
        }
        $this->removeDocument($document_id);
        $this->updateProbabilities();

        suxDB::commitTransaction($tid);
        $this->inTransaction = false;

        return true;
    }


    /**
    * @param string $document_id document id, must be unique
    * @return array|false
    */
    function getDocument($document_id) {

        $document = [];
        $st = $this->db->prepare("SELECT * FROM {$this->db_table_doc} WHERE id = ? ");
        $st->execute(array($document_id));

        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $document['id'] = $row['id'];
            $document['category_id'] = $row['bayes_categories_id'];
            $document['body'] = $row['body_plaintext'];
            return $document;
        }
        else return false;
    }


    /**
    * @return array key = ids, values = array(keys = 'category_id', 'body')
    */
    function getDocuments() {

        $st = $this->db->query("SELECT * FROM {$this->db_table_doc} ORDER BY id ASC ");

        $documents = array();
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $documents[$row['id']] = array(
                'category_id' => $row['bayes_categories_id'],
                'body' => $row['body_plaintext'],
                );
        }
        return $documents;
    }


    /**
    * @param int $vector_id vector id
    * @return array key = ids, values = array(keys = 'category_id', 'body (optional)')
    */
    function getDocumentsByVector($vector_id, $full = false) {

        if (!filter_var($vector_id, FILTER_VALIDATE_INT) || $vector_id < 1) return false;

        if ($full) $query = "SELECT {$this->db_table_doc}.* ";
        else {
            $query = "SELECT
            {$this->db_table_doc}.id,
            {$this->db_table_doc}.bayes_categories_id ";
        }

        $query .= "FROM {$this->db_table_doc}
        INNER JOIN {$this->db_table_cat} ON {$this->db_table_doc}.bayes_categories_id = {$this->db_table_cat}.id
        INNER JOIN {$this->db_table_vec} ON {$this->db_table_cat}.bayes_vectors_id = {$this->db_table_vec}.id
        WHERE {$this->db_table_vec}.id = ? ";

        $st = $this->db->prepare($query);
        $st->execute(array($vector_id));

        $documents = array();
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($full) {
                $documents[$row['id']] = array(
                    'category_id' => $row['bayes_categories_id'],
                    'body' => $row['body_plaintext'],
                    );
            }
            else {
                $documents[$row['id']] = array(
                    'category_id' => $row['bayes_categories_id'],
                    );
            }
        }

        return $documents;
    }


    /**
    * @param int $category_id category id
    * @return array key = ids, values = array(keys = 'category_id', 'body (optional)')
    */
    function getDocumentsByCategory($category_id, $full = false) {

        if (!filter_var($category_id, FILTER_VALIDATE_INT) || $category_id < 1) return false;

        if ($full) $query = "SELECT {$this->db_table_doc}.* ";
        else {
            $query = "SELECT
            {$this->db_table_doc}.id,
            {$this->db_table_doc}.bayes_categories_id ";
        }
        $query .= "FROM {$this->db_table_doc}
        INNER JOIN {$this->db_table_cat} ON {$this->db_table_doc}.bayes_categories_id = {$this->db_table_cat}.id
        WHERE {$this->db_table_cat}.id = ? ";

        $st = $this->db->prepare($query);
        $st->execute(array($category_id));

        $documents = array();
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($full) {
                $documents[$row['id']] = array(
                    'category_id' => $row['bayes_categories_id'],
                    'body' => $row['body_plaintext'],
                    );
            }
            else {
                $documents[$row['id']] = array(
                    'category_id' => $row['bayes_categories_id'],
                    );
            }
        }

        return $documents;
    }


    /**
    * @param int $category_id category id
    * @return int
    */
    function getDocumentCountByCategory($category_id) {

        if (!filter_var($category_id, FILTER_VALIDATE_INT) || $category_id < 1) return 0;

        $st = $this->db->prepare("SELECT COUNT(*) FROM {$this->db_table_doc} WHERE bayes_categories_id = ? ");
        $st->execute(array($category_id));
        return $st->fetchColumn();

    }


    // ----------------------------------------------------------------------------
    // Maths
    // ----------------------------------------------------------------------------


    /**
    * Update probabilities
    */
    function updateProbabilities() {

        // A vector is an array of categories. Probabilities must be
        // constrained to vector and not the entire tokens table. We need to
        // join tokens to categories, which contains vector_ids.

        $tid = suxDB::requestTransaction();
        $this->inTransaction = true;

        // Get vector_ids that are actually being used
        $vectors = array();
        $q = "SELECT bayes_vectors_id FROM {$this->db_table_cat} GROUP BY bayes_vectors_id ";
        $st = $this->db->query($q);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $vectors[] = $row['bayes_vectors_id'];
        }

        // Join to categories
        $q = "SELECT {$this->db_table_tok}.bayes_categories_id, SUM({$this->db_table_tok}.count) AS total
        FROM {$this->db_table_tok} INNER JOIN {$this->db_table_cat}
        ON {$this->db_table_tok}.bayes_categories_id = {$this->db_table_cat}.id
        WHERE {$this->db_table_cat}.bayes_vectors_id = ?
        GROUP BY {$this->db_table_tok}.bayes_categories_id ";

        // Constrain to individual vectors
        foreach ($vectors as $vector_id) {

            // Get the total of all known tokens
            $total_tokens = 0;

            $st = $this->db->prepare($q);
            $st->execute(array($vector_id));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $total_tokens += $row['total'];
            }

            // If there are no tokens, reset everything
            if ($total_tokens == 0) {
                $st = $this->db->prepare("UPDATE {$this->db_table_cat} SET token_count = 0, probability = 0 WHERE bayes_vectors_id = ? ");
                $st->execute(array($vector_id));
                continue;
            }

            // Get all categories
            $categories = array();
            $st = $this->db->prepare("SELECT id FROM {$this->db_table_cat} WHERE bayes_vectors_id = ? ");
            $st->execute(array($vector_id));

            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $categories[$row['id']] = true;
            }

            // Repeat $q, update probabilities
            $st = $this->db->prepare($q);
            $st->execute(array($vector_id));
            $st2 = $this->db->prepare("UPDATE {$this->db_table_cat} SET token_count = ?, probability = ? WHERE id = ? AND bayes_vectors_id = ? ");
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $proba = $row['total']/$total_tokens;
                $st2->execute(array($row['total'], $proba, $row['bayes_categories_id'], $vector_id));
                unset($categories[$row['bayes_categories_id']]);
            }

            // If there are categories with no tokens, reset those categories
            $st = $this->db->prepare("UPDATE {$this->db_table_cat} SET token_count = 0, probability = 0 WHERE id = ? AND bayes_vectors_id = ? ");
            foreach ($categories as $key => $val) {
                $st->execute(array($key, $vector_id));
            }

        }

        suxDB::commitTransaction($tid);
        $this->inTransaction = false;

    }


    /**
    * @param string $document a document
    * @param int $vector_id vector id
    * @return array key = id, values = array(keys = 'category', 'score')
    */
    function categorize($document, $vector_id) {

        if (!filter_var($vector_id, FILTER_VALIDATE_INT) || $vector_id < 1) return false;

        // Sanity check, convert to UTF-8 plaintext
        $converter = new suxHtml2UTF8($document);
        $document = $converter->getText();

        // Try to create a coherant md5 from similar documents by
        // normalizing line breaks and white spaces
        $tmp = preg_replace('/\n+/', ' ', $vector_id . $document);
        $tmp = preg_replace('/\s+/', ' ', $tmp);
        $md5 = md5($tmp);

        // Check cache
        if ($this->use_cache) {
            if ($scores = $this->checkCache($md5)) return $scores;
        }

        $scores = array();
        $categorized = array();
        $fake_prob = array();
        $total_tokens = 0;
        $ncat = 0;

        $categories = $this->getCategoriesByVector($vector_id);
        if (count($categories) <= 1) return array(); // Less than two categories, skip

        foreach ($categories as $data) {
            $fake_prob[] = $data['token_count'];
            $total_tokens += $data['token_count'];
            $ncat++;
        }

        // Fake probability
        // Using the average between the biggest and smallest token_count values
        // seems to improve results when training data is skewed
        sort($fake_prob);
        $n = count($fake_prob);
        $fake_prob = ($fake_prob[0] + $fake_prob[$n-1]) / 2;

        // Parse tokens, don't use stopwords
        $tokens = $this->parseTokens($document, false);

        // $debug1 = microtime(true);
        foreach($categories as $category_id => $data) {
            $scores[$category_id] = $data['probability'];
            foreach($tokens as $token => $count) {
                if ($this->tokenExists($token, $vector_id)) {
                    $token_count = $this->getTokenCount($token, $category_id);

                    if ($token_count && $data['token_count']) $prob = (float) $token_count/$data['token_count']; // Real probability
                    elseif ($data['token_count']) $prob = (float) 1/$fake_prob; // Fake probability
                    else $prob = 0;

                    // pow($total_tokens/$ncat, $count) is here to avoid underflow.
                    $scores[$category_id] *= $prob ** $count*($total_tokens/$ncat) ** $count;
                }
            }
            // Remember and use in reorganization of array
            $categorized[$category_id] = $data['category'];
        }
        // new dBug('Elapsed time in seconds: ' . (microtime(true) - $debug1));

        $scores = $this->rescale($scores); // Rescale
        arsort($scores); // Sort

        // Reorganize into multi-dimensional array
        foreach ($scores as $key => $val) {
            // Overwrite
            $scores[$key] = array(
                'category' => $categorized[$key],
                'score' => $scores[$key]
                );
        }

        // Cache results
        $this->cache($vector_id, $md5, $scores);

        return $scores;

    }


    /**
    * Verify if a text is higher than a given threshold
    *
    * @param float|false $threshold value between 0 and 1, or false
    * @param int $vec_id  vector id
    * @param int $cat_id  category id, related to the vector id
    * @param string $text a document to analyze
    * @return bool
    */
    function passesThreshold($threshold, $vec_id, $cat_id, $text) {

        if ($threshold === false) {
            // Top
            $score = $this->categorize($text, $vec_id);
            if ($cat_id != array_key_first($score)) return false;
        }
        elseif ($threshold > 0 && $threshold <= 1) {
            // Threshold
            $score = $this->categorize($text, $vec_id);
            if (empty($score[$cat_id])) return false;
            if (round($score[$cat_id]['score'] * 100, 2) < round($threshold *100, 2)) return false;
        }

        return true;

    }


    /**
    * @param array $scores scores (keys => category, values => scores)
    * @return array normalized scores (keys => category, values => scores)
    */
    private function rescale(array $scores)  {

        // Scale everything back to a reasonable area in
        // logspace (near zero), un-loggify, and normalize

        if (!count($scores)) return $scores;

        $total = 0.0;
        $max = (float) max($scores);

        foreach($scores as $category => $score) {
            $scores[$category] = (float) exp($score - $max);
            $total += (float) $scores[$category] ** 2;
        }
        $total = (float) sqrt($total);

        foreach($scores as $category => $score) {
            $scores[$category] = (float) $scores[$category]/$total;
        }

        reset($scores);
        return $scores;
    }

    // ----------------------------------------------------------------------------
    // Tokens
    // ----------------------------------------------------------------------------


    /**
    * @param string $string the string to get the tokens from
    * @param bool $stopwords use stopwords?
    * @return array keys = tokens, values = count
    */
    private function parseTokens($string, $stopwords = true) {

        return suxFunct::parseTokens($string, $stopwords, true);
    }


    // ----------------------------------------------------------------------------
    // Data storage, Private
    // ----------------------------------------------------------------------------


    /**
    * @param string $token token
    * @param int $vector_id vector id
    * @return bool
    */
    private function tokenExists($token, $vector_id) {

        static $st = null; // Static as cache, to make categorize() faster
        if (!$st) {
            $q = "
            SELECT COUNT(*) FROM {$this->db_table_tok}
            INNER JOIN {$this->db_table_cat} ON {$this->db_table_tok}.bayes_categories_id = {$this->db_table_cat}.id
            WHERE {$this->db_table_tok}.token = ? AND {$this->db_table_cat}.bayes_vectors_id = ?
            ";
            $st = $this->db->prepare($q);
        }

        $st->execute(array($token, $vector_id));
        return ($st->fetchColumn() > 0 ? true : false);

    }


    /** get the count of a token in a category.
    * @param string $token token
    * @param string $category_id category id
    * @return int
    */
    private function getTokenCount($token, $category_id) {

        $count = 0;

        static $st = null; // Static as cache, to make categorize() faster
        if (!$st) $st = $this->db->prepare("SELECT count FROM {$this->db_table_tok} WHERE token = ? AND bayes_categories_id = ? ");

        $st->execute(array($token, $category_id));
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $count = $row['count'];
        }

        return $count;
    }


    /**
    * @param string $token token
    * @param int $count count
    * @param string $category_id category id
    * @return bool
    */
    private function updateToken($token, $count, $category_id) {


        $token_count = $this->getTokenCount($token, $category_id);

        if ($token_count == 0) {

            $st = $this->db->prepare("INSERT INTO {$this->db_table_tok} (token, bayes_categories_id, count) VALUES (?, ?, ?) ");
            return $st->execute(array($token, $category_id, $count));

        } else {

            $st = $this->db->prepare("UPDATE {$this->db_table_tok} SET count = count + ? WHERE bayes_categories_id = ? AND token = ? ");
            $st->bindValue(1, $count, PDO::PARAM_INT);
            $st->bindValue(2, $category_id);
            $st->bindValue(3, $token);
            return $st->execute();

        }

    }


    /**
    * @param string $token token
    * @param int $count count
    * @param string $category_id category id
    * @return bool
    */
    private function removeToken($token, $count, $category_id) {

        $token_count = $this->getTokenCount($token, $category_id);

        if ($token_count != 0 && ($token_count - $count) < 1) {

            $st = $this->db->prepare("DELETE FROM {$this->db_table_tok} WHERE token = ? AND bayes_categories_id = ? ");
            return $st->execute(array($token, $category_id));

        } else {

            $st = $this->db->prepare("UPDATE {$this->db_table_tok} SET count = count - ? WHERE bayes_categories_id = ? AND token = ? ");
            $st->bindValue(1, $count, PDO::PARAM_INT);
            $st->bindValue(2, $category_id);
            $st->bindValue(3, $token);
            return $st->execute();

        }
    }



    /**
    @param string $category_id category id
    @param string $content content of the document
    @return bool
    */
    private function addDocument($category_id, $content) {

        $st = $this->db->prepare("INSERT INTO {$this->db_table_doc} (bayes_categories_id, body_plaintext) VALUES (?, ?) ");
        return $st->execute(array($category_id, $content));

    }


    /**
    * @param  string $document_id document id, must be unique
    * @return bool
    */
    protected function removeDocument($document_id) {

        $st = $this->db->prepare("DELETE FROM {$this->db_table_doc} WHERE id = ? ");
        return $st->execute(array($document_id));

    }


    // ----------------------------------------------------------------------------
    // Caching for categorization scores
    // ----------------------------------------------------------------------------

    /**
    * Purge expired cache elements
    */
    public function cleanCache() {

        $st = $this->db->prepare("DELETE FROM {$this->db_table_cache} WHERE expiration < ? ");
        $st->execute(array(time()));

    }


    /**
    * @param int $vector_id vector id
    */
    private function deleteCache($vector_id) {

        $st = $this->db->prepare("DELETE FROM {$this->db_table_cache} WHERE bayes_vectors_id = ? ");
        $st->execute(array($vector_id));

    }


    /**
    * @param  string $md5 a has of a vector id concatenated with a document
    * @return array|false
    */
    private function checkCache($md5) {

        static $st = null; // Static as cache, to make categorize() faster
        if (!$st) {
            $q = "SELECT scores FROM {$this->db_table_cache} WHERE md5 = ? ";
            $st = $this->db->prepare($q);
        }

        $st->execute(array($md5));
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            return unserialize($row['scores']);
        }
        else return false;

    }


    /**
    * @param int $vector_id vector id
    * @param string $md5 a has of a vector id concatenated with a document
    * @param array $scores
    */
    private function cache($vector_id, $md5, $scores) {

        $clean = array(
            'bayes_vectors_id' => $vector_id,
            'md5' => $md5,
            'expiration' => time() + (3600 * 12), // Keep the scores for 12 hours
            'scores' => serialize($scores),
            );

        static $st = null; // Static as cache, to make categorize() faster
        if (!$st) {
            $q = suxDB::prepareInsertQuery($this->db_table_cache, $clean);
            $st = $this->db->prepare($q);
        }

        try {
            $st->execute($clean);
        }
        catch (Exception $e) {
             // SQLSTATE 23000: Constraint violation, we don't care, carry on
            if ($st->errorCode() == 23000) return true;
            else throw ($e); // Hot potato
        }

    }


    // ----------------------------------------------------------------------------
    // Exception Handler
    // ----------------------------------------------------------------------------


    /**
    * @param Exception $e an Exception class
    */
    function exceptionHandler(\Throwable $e) {

        if ($this->db && $this->inTransaction) {
            $this->db->rollback();
            $this->inTransaction = false;
        }

        throw($e); // Hot potato!

    }


}

