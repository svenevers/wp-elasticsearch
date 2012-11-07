<?php
require_once ('ModelBase.php');
class Searcher
{
    var $elasticsearch_server_url;
    var $elastic_search_client = null;

    function __construct($elasticsearch_server_url)
    {
        spl_autoload_register(array($this, '__autoload_elastica'));
        $this->elasticsearch_server_url = $elasticsearch_server_url;
        $config = array(
            'host' => 'localhost',
            'port' => 9200
        );
        $this->elastic_search_client = new Elastica_Client(
            array(
                'url' => $this->elasticsearch_server_url
            )
        );
    }

    function __autoload_elastica($class)
    {
        $path = str_replace('_', DIRECTORY_SEPARATOR, $class);
        if (file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . $path . '.php')) {
            require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . $path . '.php');
        }
    }

    function search($query, $facets = array(), $offset = false, $limit = false, $indexType = false)
    {
        $global_query = $query;
        $query = $global_query['s'];
        // Define a Query. We want a string query.
        $elasticaQueryString = new Elastica_Query_QueryString();
        $elasticaQueryString->setQuery($query);

        $elasticaFilterAnd 	= new Elastica_Filter_And();

        $tagTerm = $global_query['tags'];
        $authorTerm = $global_query['author'];
        $catTerm = $global_query['cats'];
        if (!empty($tagTerm)) {
            $filterTag	= new Elastica_Filter_Term();
            $filterTag->setTerm('tags', $tagTerm);
            $elasticaFilterAnd->addFilter($filterTag);
        }

        if (!empty($authorTerm)) {
            $filterAuthor	= new Elastica_Filter_Term();
            $filterAuthor->setTerm('author', $authorTerm);
            $elasticaFilterAnd->addFilter($filterAuthor);
        }

        if (!empty($catTerm)) {
            $filterCat	= new Elastica_Filter_Term();
            $filterCat->setTerm('cats', $catTerm);
            $elasticaFilterAnd->addFilter($filterCat);
        }

        // Create the actual search object with some data.
        $elasticaQuery = new Elastica_Query();
        $elasticaQuery->setQuery($elasticaQueryString);
        if (!empty($tagTerm) || !empty($authorTerm) || !empty($catTerm)) {
            $elasticaQuery->setFilter($elasticaFilterAnd);
        }
        if ($offset) {
            $elasticaQuery->setFrom($offset);
        }
        if ($limit) {
            $elasticaQuery->setLimit($limit);
        }

        //Check facet fields
        if (!empty($facets)) {
            $facet_arr = array();
            foreach ($facets as $facet) {
                ${$facet . "_facet"} = new Elastica_Facet_Terms($facet);
                ${$facet . "_facet"}->setField($facet);
                ${$facet . "_facet"}->setSize(10);
                ${$facet . "_facet"}->setOrder('reverse_count');
                array_push($facet_arr, ${$facet . "_facet"});
            }
            $elasticaQuery->setFacets($facet_arr);
        }
        //Search on the index.
        if ($indexType) {
            $elasticaResultSet = $this->elastic_search_client->getIndex(ModelBase::$_INDEX)->getType($indexType)->search($elasticaQuery);
        } else {
            $elasticaResultSet = $this->elastic_search_client->getIndex(ModelBase::$_INDEX)->search($elasticaQuery);
        }
        return $elasticaResultSet;
    }

    /**
     * @param $global_query
     * @param $type it can be s, cats, author, tags
     * @return mixed
     */
    function extract_query_string($global_query, $type)
    {
        $query_args = explode("&", $global_query);
        $search_query = array();

        foreach ($query_args as $key => $string) {
            $query_split = explode("=", $string);
            $search_query[$query_split[0]] = urldecode($query_split[1]);
        }
        return $search_query[$type];
    }
}