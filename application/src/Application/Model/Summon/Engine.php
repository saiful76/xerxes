<?php

/*
 * This file is part of Xerxes.
 *
 * (c) California State University <library@calstate.edu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Application\Model\Summon;

use Application\Model\Search;
use Xerxes\Summon;
use Xerxes\Utility\Factory;
use Xerxes\Utility\Parser;
use Xerxes\Mvc\Request;

/**
 * Summon Search Engine
 * 
 * @author David Walker <dwalker@calstate.edu>
 */

class Engine extends Search\Engine 
{
	protected $summon_client; // summon client
	protected $formats_exclude = array(); // formats configured to exclude

	/**
	 * Constructor
	 */
	
	public function __construct()
	{
		parent::__construct();
		
		$id = $this->config->getConfig("SUMMON_ID", true);
		$key = $this->config->getConfig("SUMMON_KEY", true);		
				
		$this->summon_client = new Summon($id, $key, Factory::getHttpClient());
		
		// formats to exclude
		
		$this->formats_exclude = explode(',', $this->config->getConfig("EXCLUDE_FORMATS") );
	}
	
	/**
	 * Return the total number of hits for the search
	 * 
	 * @return int
	 */	
	
	public function getHits( Search\Query $search )
	{
		// get the results
		
		$results = $this->doSearch( $search, 1, 0 );

		// return total
		
		return $results->getTotal();
	}

	/**
	 * Search and return results
	 * 
	 * @param Query $search		search object
	 * @param int $start							[optional] starting record number
	 * @param int $max								[optional] max records
	 * @param string $sort							[optional] sort order
	 * 
	 * @return Results
	 */	
	
	public function searchRetrieve( Search\Query $search, $start = 1, $max = 10, $sort = "")
	{
		// cache
		
		$results = $this->getCachedResults($search);
		
		if ( $results == null )
		{
			$results = $this->doSearch( $search, $start, $max, $sort);
			$this->setCachedResults($results, $search);
		}
		
		if ( $this->config->getConfig('mark_fulltext_using_export', false, false ) )
		{
			$results->markFullText(); // sfx data
		}
		
		return $results;
	}	
	
	/**
	 * Return an individual record
	 * 
	 * @param string	record identifier
	 * @return Results
	 */
	
	public function getRecord( $id )
	{
		// get result
		
		$results = $this->doGetRecord( $id );
		
		$results->getRecord(0)->addRecommendations(); // bx
		
		if ( $this->config->getConfig('mark_fulltext_using_export', false, false ) )
		{
			$results->markFullText(); // sfx data
		}
		
		return $results;
	}

	/**
	 * Get record to save
	 * 
	 * @param string	record identifier
	 * @return int		internal saved id
	 */	
	
	public function getRecordForSave( $id )
	{
		return $this->doGetRecord($id);
	}
	
	/**
	 * Do the actual fetch of an individual record
	 * 
	 * @param string	record identifier
	 * @return Results
	 */	
	
	protected function doGetRecord( $id )
	{
		if ( $id == "" )
		{
			throw new \DomainException('No record ID supplied');
		}
		
		// always set to authenticated if you know the id
		
		$this->summon_client->setToAuthenticated();
		
		// get result
		
		$summon_results = $this->summon_client->getRecord($id);
		$results = $this->parseResponse($summon_results);
		
		return $results;
	}		
	
	/**
	 * Do the actual search
	 * 
	 * @param Query $search		search object
	 * @param int $start							[optional] starting record number
	 * @param int $max								[optional] max records
	 * @param string $sort							[optional] sort order
	 * 
	 * @return Results
	 */		
	
	protected function doSearch( Search\Query $search, $start = 1, $max = 10, $sort = "")
	{
		// limit to local users?
		
		if ( $search->getUser()->isAuthorized() )
		{
			$this->summon_client->setToAuthenticated();
		}
		
		// prepare the query
		
		$query = $search->toQuery();
		
		// facets to include in the response
		
		foreach ( $this->config->getFacets() as $facet_config )
		{
			if ( $facet_config['type'] == 'date' )
			{
				$this->summon_client->setDateRangesToInclude((string) $facet_config["ranges"]);
			}
			else
			{
				$this->summon_client->includeFacet( (string) $facet_config["internal"] .",or,1," . (string) $facet_config["max"] ); 
			}
		}
		
		// limit to local holdings unless told otherwise
		
		if ( $this->config->getConfig('LIMIT_TO_HOLDINGS', false) )
		{
			$this->summon_client->limitToHoldings();
		}

		// limits
		
		foreach ( $search->getLimits(true) as $limit )
		{
			if ( $limit->field == 'newspapers')
			{
				continue; // we'll handle you later
			}
			
			// holdings only
			
			if ( $limit->field == 'holdings' )
			{
				if ( $limit->value == 'false')
				{
					// this is actually an expander to search everything
					
					$this->summon_client->limitToHoldings(false);
				}
				else
				{
					$this->summon_client->limitToHoldings();
				}
			}
			
			// date type
			
			elseif ( $this->config->getFacetType($limit->field) == 'date' )
			{
				// @todo: make this not 'display'
				
				if ( $limit->value == 'start' && $limit->display != '')
				{
					$this->summon_client->setStartDate($limit->display);
				}
				elseif ( $limit->value == 'end' && $limit->display != '')
				{
					$this->summon_client->setEndDate($limit->display);
				}
			}
			
			// regular type
			
			else
			{
				$value = '';
				$boolean = 'false';
					
				if ( $limit->boolean == "NOT" )
				{
					$boolean = 'true';
				}
				
				// multi-select filter
					
				if ( is_array($limit->value) )
				{
					// exclude
					
					if ( $boolean == 'true' ) 
					{
						foreach ( $limit->value as $limited )
						{
							$value = str_replace(',', '\,', $limited) ;
							$this->summon_client->addFilter($limit->field . ",$value,$boolean");
						}
					}
					
					// inlcude
					
					else
					{
						foreach ( $limit->value as $limited )
						{
							$value .= ',' . str_replace(',', '\,', $limited);
						}
					
						$this->summon_client->addComplexFilter($limit->field . ',' . $boolean . $value);
					}
				}
				
				// regular filter
				
				else
				{
					$value = str_replace(',', '\,', $limit->value);
					$this->summon_client->addFilter($limit->field . ",$value,$boolean");
				}
			}
		}

		// format filters
		
		// newspapers are a special case, i.e., they can be optional
		
		if ( $this->config->getConfig('NEWSPAPERS_OPTIONAL', false) )
		{
			 $news_limit = $search->getLimit('facet.newspapers');
			
			if ( $news_limit->value != 'true' )
			{
				$this->formats_exclude[] = 'Newspaper Article';
			}
		}

		// always exclude these
		
		foreach ( $this->formats_exclude as $format )
		{
			$this->summon_client->addFilter("ContentType,$format,true");
		}
		
		// summon deals in pages, not start record number
		
		if ( $max > 0 )
		{
			$page = ceil ($start / $max);
		}
		else
		{
			$page = 1;
		}
		
		// get the results
		
		$summon_results = $this->summon_client->query($query, $page, $max, $sort);
		
		return $this->parseResponse($summon_results);
	}
	
	/**
	 * Parse the summon response
	 *
	 * @param array $summon_results		summon results array from client
	 * @return ResultSet
	 */
	
	protected function parseResponse($summon_results)
	{
		// testing
		// header("Content-type: text/plain"); print_r($summon_results); exit;	

		// nada
		
		if ( ! is_array($summon_results) )
		{
			throw new \Exception("Cannot connect to Summon server");
		}		
		
		// just an error, so throw it
		
		if ( ! array_key_exists('recordCount', $summon_results) && array_key_exists('errors', $summon_results) )
		{
			$message = $summon_results['errors'][0]['message'];
			
			throw new \Exception($message);
		}
		
		// results
		
		$result_set = new ResultSet($this->config);
		
		
		// recommendations
		
		$databases = $this->extractRecommendations($summon_results);
		
		foreach ( $databases as $database )
		{
			$result_set->addRecommendation($database);
		}

		// total
		
		$total = $summon_results['recordCount'];
		$result_set->total = $total;
		
		// extract records
		
		foreach ( $this->extractRecords($summon_results) as $xerxes_record )
		{
			$result_set->addRecord($xerxes_record);
		}
		
		// extract facets
		
		$facets = $this->extractFacets($summon_results);
		$result_set->setFacets($facets);
		
		return $result_set;
	}

	/**
	 * Parse out database recommendations
	 * 
	 * @param array $summon_results
	 * @return Database[]|Resource[]
	 */
	
	protected function extractRecommendations($summon_results)
	{
		$recommend = array();
		
		$recommendations = $summon_results['recommendationLists'];
		
		if ( array_key_exists('database', $recommendations) )
		{
			foreach ( $recommendations['database'] as $database_array )
			{
				$recommend[] = new Database($database_array);
			}
		}
		
		if ( array_key_exists('bestBet', $recommendations) )
		{
			foreach ( $recommendations['bestBet'] as $database_array )
			{
				$recommend[] = new Resource($database_array);
			}
		}		
		
		return $recommend;
	}
	
	/**
	 * Parse records out of the response
	 *
	 * @param array $summon_results
	 * @return Record[]
	 */
	
	protected function extractRecords($summon_results)
	{
		$records = array();
		
		if ( array_key_exists("documents", $summon_results) )
		{
			foreach ( $summon_results["documents"] as $document )
			{
				$xerxes_record = new Record();
				$xerxes_record->load($document);
				array_push($records, $xerxes_record);
			}
		}
		
		return $records;
	}
	
	/**
	 * Parse facets out of the response
	 *
	 * @param array $summon_results
	 * @return Facets
	 */	
	
	protected function extractFacets($summon_results)
	{
		$facets = new Search\Facets();
		
		$facet_fields = array();
		
		if ( array_key_exists('facetFields', $summon_results) )
		{
			$facet_fields = $summon_results['facetFields'];
		}
		
		if ( array_key_exists('rangeFacetFields', $summon_results) )
		{
			foreach ( $summon_results['rangeFacetFields'] as $range_facet )
			{
				$facet_fields[] = $range_facet;
			}
		}
		
		if ( count($facet_fields) > 0 )
		{		
			// @todo: figure out how to factor out some of this to parent class
			
			// take them in the order defined in config
				
			foreach ( $this->config->getFacets() as $group_internal_name => $config )
			{
				foreach ( $facet_fields as $facetFields )
				{
					if ( $facetFields["displayName"] == $group_internal_name)
					{
						$group = new Search\FacetGroup();
						$group->name = $facetFields["displayName"];
						$group->public = $this->config->getFacetPublicName($facetFields["displayName"]);
						
						if ( $config['display'] == 'false' )
						{
							$group->display = 'false';
						}
							
						$facets->addGroup($group);
						
						// date type
						
						if ( (string) $config["type"] == "date")
						{
							foreach ( $facetFields["counts"] as $counts )
							{
								$start_date = $counts['range']['minValue'];
								$end_date = $counts['range']['maxValue'];
								
								
								$facet = new Search\Facet();
								$facet->name = "$start_date-$end_date";
								$facet->count = $counts["count"];
								$facet->key = "$start_date:$end_date";
								$facet->is_date = true;
									
								$group->addFacet($facet);
							}
						
						}
						else // regular
						{
							foreach ( $facetFields["counts"] as $counts )
							{
								// skip excluded facets
								
								if ( $group->name == 'ContentType' && in_array($counts["value"], $this->formats_exclude) )
								{
									continue;
								}
								
								$facet = new Search\Facet();
								$facet->name = $counts["value"];
								$facet->count = $counts["count"];
								
								if ( $counts['isNegated'] == '1')
								{
									$facet->is_excluded = 1;
								}
								
								$group->addFacet($facet);
							}
						}
					}
				}
			}
		}
		
		return $facets;
	}
	
	/**
	 * Return the search engine config
	 *
	 * @return Config
	 */
	
	public function getConfig()
	{
		return Config::getInstance();
	}	
	
	/**
	 * Return the Solr search query object
	 *
	 * @return Query
	 */
	
	public function getQuery(Request $request )
	{
		if ( $this->query instanceof Query )
		{
			return $this->query;
		}
		else
		{
			return new Query($request, $this->getConfig());
		}
	}
}
