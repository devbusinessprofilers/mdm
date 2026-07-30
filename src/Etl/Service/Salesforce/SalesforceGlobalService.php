<?php

namespace App\Etl\Service\Salesforce;

use Psr\Cache\CacheItemPoolInterface;

// SF6

class SalesforceGlobalService
{
    public const UPDATABLE_FIELDS = [
        'Contrat_avec_comptes_Achat_normal__c',
        'Contrat_avec_comptes_Mise_en_relation__c',
        'Contrat_avec_comptes_Ni_com_ni_frais__c',
        'RSE_Achats_responsables__c',
        'RSE_Impact_environnemental__c',
        'RSE_Impact_social__c',
        'RSE_Mobilite__c',
        'RSE_Labels_et_demarche_qualite__c',
        'RSE_Note_globale__c',
        'Type_de_procedure_judiciaire__c',
        'Evaluation_client__c'
    ];

    /** @var SalesforceHttpClientService */
    private $salesforceHttpClientService;

    /** @var CacheItemPoolInterface */
    private $cache;

    /** @var string  */
    private $salesforceEnv;

    public function __construct(SalesforceHttpClientService $salesforceHttpClientService, CacheItemPoolInterface $cache, string $salesforceEnv)
    {
        $this->salesforceHttpClientService = $salesforceHttpClientService;
        $this->cache= $cache;
        $this->salesforceEnv = $salesforceEnv;
    }

    public function getGlobalInfos()
    {
        $query = "SELECT DefaultAccountId__c, PricebookId__c FROM PortailBP_Parametres__mdt WHERE QualifiedApiName='" . $this->salesforceEnv . "'";
        $url = "/services/data/" . SalesforceHttpClientService::VERSION . "/query?q=" . urlencode($query);
        $response = $this->salesforceHttpClientService->get($url);

        if(isValidJson($response)) {
            $response = json_decode($response);
        } else if(isValidXml($response)) {
            $response = simplexml_load_string($response);
        } else {
            return null;
        }

        $item = $this->cache->getItem('globalInfos');
        if (!$item->isHit()) {
            $item->set($response->records);
            $this->cache->save($item);
        }

        return $item->get('value')[0];
    }

    public function getPriceBookId()
    {
        $globals = $this->getGlobalInfos();
        if ($globals) {
            return (string)$globals->PricebookId__c;
        }
        return (string)false;
    }

    public function getDefaultAccountId()
    {
        $globals = $this->getGlobalInfos();
        if ($globals) {
            return (string)$globals->DefaultAccountId__c;
        }
        return (string)false;
    }

    /**
     * ID of the product price record in the "price book" (Salesforce price list) used by the portal to create opportunity lines.
     *
     */
    public function getPriceBookEntryIds()
    {
        $priceBookId = $this->getPriceBookId();

        $query = "SELECT Id, Product2.Id, Product2.ID__c, Product2.Name, Product2.Photo_principale__c FROM PricebookEntry WHERE IsActive=TRUE AND Product2.IsActive=TRUE AND Product2.ID__c<>'' AND Product2.ID__c<>NULL AND Pricebook2Id='" . $priceBookId ."' ORDER BY Product2.ID__c";
        $url = "/services/data/" . SalesforceHttpClientService::VERSION . "/query?q=" . urlencode($query);
        $response = $this->salesforceHttpClientService->get($url);

        if(isValidJson($response)) {
            $response = json_decode($response);
        } else if(isValidXml($response)) {
            $response = simplexml_load_string($response);
        } else {
            return null;
        }

        $list = [];
        $i = 0;
        while ($i <= sizeof($response->records) - 1) {
            array_push($list, $response->records[$i]);
            $i++;
        }

        while ( property_exists($response, 'nextRecordsUrl') && $response->nextRecordsUrl ) {
            $response = $this->salesforceHttpClientService->get($response->nextRecordsUrl);

            if(isValidJson($response)) {
                $response = json_decode($response);
            } elseif(isValidXml($response)) {
                $response = simplexml_load_string($response);
            } else {
                return null;
            }
            $i = 0;
            while ($i <= sizeof($response->records) - 1) {
                array_push($list, $response->records[$i]);
                $i++;
            }
        }

        return $list;
    }

    public function getProductsForUpdate(): ?array
    {
        $fields = implode(', ', self::UPDATABLE_FIELDS);
        $query = sprintf(
            "SELECT Id, ID__c, %s FROM Product2 WHERE (%s) AND ID__c != NULL",
            $fields,
            implode(' OR ', array_map(static fn(string $field) => $field . ' != NULL', self::UPDATABLE_FIELDS))
        );

        $url = "/services/data/" . SalesforceHttpClientService::VERSION . "/query?q=" . urlencode($query);
        $response = $this->salesforceHttpClientService->get($url);

        if(isValidJson($response)) {
            $response = json_decode($response);
        } elseif(isValidXml($response)) {
            $response = simplexml_load_string($response);
        } else {
            return null;
        }

        $i = 0;
        $list = [];
        while ($i <= sizeof($response->records) - 1) {
            $record = $response->records[$i];
            $list[] = $record;
            $i++;
        }

        // Gestion de la pagination
        while (property_exists($response, 'nextRecordsUrl') && $response->nextRecordsUrl) {
            $response = $this->salesforceHttpClientService->get($response->nextRecordsUrl);

            if(isValidJson($response)) {
                $response = json_decode($response);
            } elseif(isValidXml($response)) {
                $response = simplexml_load_string($response);
            } else {
                return null;
            }

            $i = 0;
            while ($i <= sizeof($response->records) - 1) {
                $record = $response->records[$i];
                $list[] = $record;
                $i++;
            }
        }

        return $list;
    }

    /**
     * Return products list with RSE notation.
     *
     */
    public function getRSEEntryIds()
    {
        $query = "SELECT Id, ID__c, RSE_Achats_responsables__c, RSE_Impact_environnemental__c, RSE_Impact_social__c, RSE_Mobilite__c, RSE_Labels_et_demarche_qualite__c, RSE_Note_globale__c FROM Product2 WHERE RSE_Note_globale__c != NULL";

        $url = "/services/data/" . SalesforceHttpClientService::VERSION . "/query?q=" . urlencode($query);

        $response = $this->salesforceHttpClientService->get($url);

        if(isValidJson($response)) {
            $response = json_decode($response);
        } elseif(isValidXml($response)) {
            $response = simplexml_load_string($response);
        } else {
            return null;
        }

        $list = [];
        $i = 0;
        while ($i <= sizeof($response->records) - 1) {
            array_push($list, $response->records[$i]);
            $i++;
        }

        while ( property_exists($response, 'nextRecordsUrl') && $response->nextRecordsUrl ) {
            $response = $this->salesforceHttpClientService->get($response->nextRecordsUrl);

            if(isValidJson($response)) {
                $response = json_decode($response);
            } else if(isValidXml($response)) {
                $response = simplexml_load_string($response);
            } else {
                return null;
            }

            $i = 0;
            while ($i <= sizeof($response->records) - 1) {
                array_push($list, $response->records[$i]);
                $i++;
            }
        }

        return $list;
    }

    /**
     * Return products list with reviews
     * @return array|null
     */
    public function getReviewEntryId(): ?array
    {
        $query = "SELECT id, ID__c, Evaluation_client__c FROM Product2 WHERE Evaluation_client__c != NULL";

        $url = "/services/data/" . SalesforceHttpClientService::VERSION . "/query?q=" . urlencode($query);
        $response = $this->salesforceHttpClientService->get($url);

        if(isValidJson($response)) {
            $response = json_decode($response);
        } elseif(isValidXml($response)) {
            $response = simplexml_load_string($response);
        } else {
            return null;
        }

        $list = [];
        $i = 0;
        while ($i <= sizeof($response->records) - 1) {
            array_push($list, $response->records[$i]);
            $i++;
        }

        while ( property_exists($response, 'nextRecordsUrl') && $response->nextRecordsUrl ) {
            $response = $this->salesforceHttpClientService->get($response->nextRecordsUrl);

            if(isValidJson($response)) {
                $response = json_decode($response);
            } elseif(isValidXml($response)) {
                $response = simplexml_load_string($response);
            } else {
                return null;
            }

            $i = 0;
            while ($i <= sizeof($response->records) - 1) {
                array_push($list, $response->records[$i]);
                $i++;
            }
        }

        return $list;
    }

    /**
     * Return actives SF products list.
     *
     */
    public function getProductEntryIds($selectClauseCplt, $whereClause, $limit = 0)
    {
        $selectedFields = 'Id, ID__c, IsActive';
        if($selectClauseCplt && $selectClauseCplt != '') {
            $selectedFields = $selectedFields . ', ' . $selectClauseCplt;
        }

        $query = "SELECT " . $selectedFields . " FROM Product2";

        if($whereClause && $whereClause != '') {
            $query = $query . " AND " . $whereClause;
        }

        if($limit && $limit > 0) {
            $query = $query . " LIMIT " . $limit;
        }
        $url = "/services/data/" . SalesforceHttpClientService::VERSION . "/query?q=" . urlencode($query);

        $response = $this->salesforceHttpClientService->get($url);

        if(isValidJson($response)) {
            $response = json_decode($response);
        } elseif(isValidXml($response)) {
            $response = simplexml_load_string($response);
        } else {
            return null;
        }

        $list = [];
        $i = 0;
        while ($i <= sizeof($response->records) - 1) {
            array_push($list, $response->records[$i]);
            $i++;
        }

        while ( property_exists($response, 'nextRecordsUrl') && $response->nextRecordsUrl ) {
            $response = $this->salesforceHttpClientService->get($response->nextRecordsUrl);

            if(isValidJson($response)) {
                $response = json_decode($response);
            } elseif(isValidXml($response)) {
                $response = simplexml_load_string($response);
            } else {
                return null;
            }

            $i = 0;
            while ($i <= sizeof($response->records) - 1) {
                array_push($list, $response->records[$i]);
                $i++;
            }
        }

        return $list;
    }

    public function updateProviderMainPhoto($product2Id, $path)
    {
        $body = [
            'Photo_principale__c' => $path
        ];
        $url = "/services/data/" . SalesforceHttpClientService::VERSION . "/sobjects/Product2/" . $product2Id;

        $response = $this->salesforceHttpClientService->patch($url, $body);

        if($response !== '') {
            return(false);
        }

        return true;
    }

    public function execSOQL($request)
    {
        $url = "/services/data/" . SalesforceHttpClientService::VERSION . "/query?q=" . urlencode($request);

        $response = $this->salesforceHttpClientService->get($url);

        return $response;
    }

    /**
     * Normalizes a Salesforce record by ensuring all expected fields are present.
     *
     * Since the Salesforce REST API omits null fields from its JSON response,
     * this method fills in any missing fields with a null value, guaranteeing
     * a consistent data structure regardless of what Salesforce returns.
     *
     * @param \stdClass $record         The raw record object returned by the Salesforce API
     * @param array     $expectedFields The list of field names that should be present on the record
     *
     * @return \stdClass The normalized record with all expected fields set (null if missing)
     */
    public function normalizeRecord(\stdClass $record, array $expectedFields): \stdClass
    {
        foreach ($expectedFields as $field) {
            if (!isset($record->$field)) {
                $record->$field = null;
            }
        }
        return $record;
    }
}
