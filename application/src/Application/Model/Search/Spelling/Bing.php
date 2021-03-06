<?php

/*
 * This file is part of Xerxes.
 *
 * (c) California State University <library@calstate.edu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Application\Model\Search\Spelling;

use Xerxes\Utility\Factory;
use Xerxes\Utility\Parser;
use Xerxes\Utility\Registry;

/**
 * Bing Spell Checker
 *
 * @author David Walker <dwalker@calstate.edu>
 */

class Bing
{
	/**
	 * Check spelling
	 * 
	 * @param QueryTerms[] $query_terms
	 */
	
	public function checkSpelling(array $query_terms)
	{
		$registry = Registry::getInstance();
		$app_id = $registry->getConfig('BING_ID');
		
		$suggestion = new Suggestion();
		
		if ( $app_id != null )
		{
			$client = Factory::getHttpClient();
				
			// @todo: see if we can't collapse multiple terms into a single spellcheck query
			
			foreach ( $query_terms as $term )
			{
				$query = $term->phrase;
				$query = urlencode(trim($query));
			
				$correction = null;
			
				// get spell suggestion
			
				try
				{
					$url = "http://api.search.live.net/xml.aspx?Appid=$app_id&sources=spell&query=$query";
			
					$client->setUri($url);
					$response = $client->send()->getBody();
			
					// process it
						
					$xml = Parser::convertToDOMDocument($response);
						
					// echo header("Content-Type: text/xml"); echo $xml->saveXML(); exit;
						
					$suggestion_node = $xml->getElementsByTagName('Value')->item(0);
						
					if ( $suggestion_node != null )
					{
						$correction = $suggestion_node->nodeValue;
					}
						
				}
				catch (\Exception $e)
				{
					throw $e; // @todo: remove after testing
						
					trigger_error('Could not process spelling suggestion: ' . $e->getTraceAsString(), E_USER_WARNING);
				}
			
				// got one
			
				if ( $correction != null )
				{
					$term->phrase = $suggestion_node->nodeValue;
					
					$suggestion->addTerm($term);
				}
			}
		}
		
		return $suggestion;
	}
}
