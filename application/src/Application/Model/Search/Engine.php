<?php

/*
 * This file is part of Xerxes.
 *
 * (c) California State University <library@calstate.edu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Application\Model\Search;

use Xerxes\Utility\Cache;
use Xerxes\Utility\Registry;
use Xerxes\Mvc\Request;

/**
 * Search Engine
 *
 * @author David Walker <dwalker@calstate.edu>
 */

abstract class Engine
{
	/**
	 * identifier of this search engine
	 * 
	 * @var string
	 */
	
	public $id;
	
	/**
	 * url to the search service
	 * 
	 * @var string
	 */
	
	protected $url;
	
	/**
	 * @var Registry
	 */
	
	protected $registry;
	
	/**
	 * @var Config
	 */
	
	protected $config;
	
	/**
	 * @var Query
	 */
	
	protected $query;
	
	/**
	 * @var Cache
	 */
	
	protected $cache;
	
	/**
	 * Constructor
	 */
	
	public function __construct()
	{
		$this->cache = new Cache();
		
		// application config
		
		$this->registry = Registry::getInstance();
		
		// local config
		
		$this->config = $this->getConfig();
	}
	
	/**
	 * Return the total number of hits for the search
	 * 
	 * @return int
	 */	
	
	abstract public function getHits( Query $search );
	
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
	
	abstract public function searchRetrieve( Query $search, $start = 1, $max = 10, $sort = "" );
	
	/**
	 * Return an individual record
	 * 
	 * @param string	record identifier
	 * @return Results
	 */
	
	abstract public function getRecord( $id );

	/**
	 * Get record to save
	 * 
	 * @param string	record identifier
	 * @return int		internal saved id
	 */	
	
	abstract public function getRecordForSave( $id );
	
	/**
	 * Return the search engine config
	 * 
	 * @return Config
	 */
	
	abstract public function getConfig();
	
	/**
	 * Return the URL sent ot the web service
	 * 
	 * @return string
	 */
	
	public function getURL()
	{
		return $this->url;
	}
	
	/**
	 * Return a search query object
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
	
	/**
	 * Check for previously cached results
	 * 
	 * @param string|Query $query
	 * @return null|ResultSet     null if no previously cached results
	 */
	
	public function getCachedResults($query)
	{
		// if cache is turned off, then don't bother looking up cache
		
		if ( $this->config->getConfig('CACHE_RESULTS', false, true) == false )
		{
			return null;
		}
		
		$id = $this->getResultsID($query);
		
		return $this->cache->get($id);
	}
	
	/**
	 * Cache search results
	 * 
	 * @param ResultSet $results
	 * @param string|Query $query
	 */
	
	public function setCachedResults(ResultSet $results, $query)
	{
		// if cache is turned off, then don't bother caching
		
		if ( $this->config->getConfig('CACHE_RESULTS', false, true) == false )
		{
			return null;
		}		
		
		$id = $this->getResultsID($query);
		
		$this->cache->set($id, $results);
	}
	
	/**
	 * calculate query identifier
	 * 
	 * @param string|Query $query
	 */
	
	protected function getResultsID($query)
	{
		if ( $query == '' )
		{
			throw new \DomainException("Query ID cannot be empty");
		}
		
		$id = 'results';
		
		if ( $query instanceof Query)
		{
			$id .= $query->getUrlHash();
		}
		else
		{
			$id .= $query;
		}
		
		return $id;
	}
}
