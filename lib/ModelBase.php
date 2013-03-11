<?php
abstract class ModelBase
{

    var $serverUrl = null; //It should be end with "/". https://github.com/ruflin/Elastica/issues/120#issuecomment-3423869
    var $elasticaClient = null;
    var $documentToIndex = null;
    var $documentType = null;
    var $documentIndex = null;
    var $documentPrefix = null;

    public static $_CHUNK_SIZE = 1000;

    function initialize()
    {
        spl_autoload_register(array($this, '__autoload_elastica'));
        $this->elasticaClient = new Elastica_Client(
            array(
                'url' => $this->serverUrl
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

    /**
     * Index document by using its id. documentPrefix is used for making index unique
     * among all objects(post, comment, user, etc...)
     */
    public function index($bulk = false)
    {
        if (!empty($this->documentToIndex)) {
            if ($this->elasticClient == null) {
                $this->initialize();
            }
            $elasticaIndex = $this->elasticaClient->getIndex($this->documentIndex);
            $elasticaType = $elasticaIndex->getType($this->documentType);
            if ($bulk) {
                $i = 0;
                foreach ($this->documentToIndex as $doc) {
                    $documents[] = new Elastica_Document($this->documentPrefix . $doc['id'], $doc);
                    $i++;
                    //bulk index is better than unit index.
                    if ($i % ModelBase::$_CHUNK_SIZE == 0) {
                        $elasticaType->addDocuments($documents);
                        $documents = array();
                    }
                }
                if (!empty($documents)) {
                    $elasticaType->addDocuments($documents);
                }
            } else {
                $document = new Elastica_Document($this->documentPrefix . $this->documentToIndex['id'], $this->documentToIndex);
                $elasticaType->addDocument($document);
            }
            $elasticaType->getIndex()->refresh();
        }
    }

    /**
     * Delete specific index
     * @param $documentId
     */
    public function delete($documentId)
    {
        if ($this->elasticClient == null) {
            $this->initialize();
        }
        $elasticaIndex = $this->elasticaClient->getIndex($this->documentIndex);
        $elasticaType = $elasticaIndex->getType($this->documentType);
        $elasticaType->deleteById($documentId);
        $elasticaType->getIndex()->refresh();
    }

    /**
     * Delete entire type(all indexes)
     */
    public function deleteAll()
    {
        if ($this->elasticClient == null) {
            $this->initialize();
        }
        $elasticaIndex = $this->elasticaClient->getIndex($this->documentIndex);
        $elasticaType = $elasticaIndex->getType($this->documentType);
        $elasticaType->delete();
        $elasticaType->getIndex()->refresh();
    }


    /**
     * Check index existance
     */
    public function checkIndexExists()
    {
        $url = $this->serverUrl . $this->documentIndex;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
        $result = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return $http_status;
    }

    /**
     * Check index existance
     */
    public function checkServerStatus()
    {
        $url = $this->serverUrl;
        $result = $this->executeRequest(array(CURLOPT_URL => $url, CURLOPT_HEADER => false, CURLOPT_CUSTOMREQUEST => 'GET', CURLOPT_RETURNTRANSFER => CURLOPT_RETURNTRANSFER));
        $statusData = json_decode($result, true);
        return ($statusData['status'] == '200');
    }

    /**
     * Create index with curl
     */
    public function createIndexName()
    {
        $url = $this->serverUrl . $this->documentIndex;
        $body = '{
            "settings": {
                "index": {
                    "number_of_replicas": 1,
                    "number_of_shards": 1
                },
                "analysis": {
                    "analyzer": {
                        "default": {
                            "type" : "snowball",
                            "language" : "English"
                        },
                        "autocomplete": {
                            "tokenizer": "lowercase",
                            "filter": ["edge_ngram"]
                        }
                    },
                    "filter": {
                        "edge_ngram": {
                            "side": "front",
                            "max_gram": 10,
                            "min_gram": 3,
                            "type": "edgeNGram"
                        }

                    }
                }
            },
            "mappings": {
                "post": {
                    "_all": {
                        "enabled": false
                    },"_source": {
                        "enabled": false
                    },
                    "properties": {
                        "id": {
                            "type": "long",
                            "index": "not_analyzed"
                        },
                        "content": {
                            "type": "string",
                            "_boost" : 1.0
                        },
                        "date": {
                            "index": "not_analyzed",
                            "type": "date",
                            "format": "yyyy-MM-dd HH:mm:ss"
                        },
                        "author": {
                            "index": "not_analyzed",
                            "type": "string"
                        },
                        "title": {
                            "analyzer": "autocomplete",
                            "type": "string",
                            "_boost" : 3.0,
                            "store" : "yes"
                        },
                        "uri": {
                            "type": "string",
                            "index": "not_analyzed"
                        },
                        "cats": {
                            "type": "string",
                            "index": "not_analyzed",
                            "index_name": "cat"
                        },
                        "tags": {
                            "type": "string",
                            "index": "not_analyzed",
                            "index_name": "tag"
                        }
                    }
                }
            }
        }';

        return $this->executeRequest(array(CURLOPT_URL => $url, CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POSTFIELDS => $body));
    }

    /**
     * Check index count
     */
    public function checkIndexCount()
    {
        $url = $this->serverUrl . $this->documentIndex . '/_stats';
        $response = $this->executeRequest(array(CURLOPT_URL => $url, CURLOPT_HEADER => false, CURLOPT_CUSTOMREQUEST => 'GET', CURLOPT_RETURNTRANSFER => true));
        $indexData = json_decode($response, true);
        return $indexData['_all']['primaries']['docs']['count'];
    }

    function executeRequest(array $opts)
    {
        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}
