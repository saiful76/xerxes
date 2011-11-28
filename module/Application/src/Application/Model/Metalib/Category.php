<?php

/**
 * Metalib Category
 *
 * @author David Walker
 * @copyright 2011 California State University
 * @link http://xerxes.calstate.edu
 * @license http://www.gnu.org/licenses/
 * @version $Id$
 * @package Xerxes
 */

class Xerxes_Model_Metalib_Category extends Xerxes_Framework_DataValue
{
	public $id;
	public $name;
	public $normalized;
	public $old;
	public $lang;
	public $subcategories = array();
	public $sidebar = array();
	
	/**
	 * Converts a sting to a normalized (no-spaces, non-letters) string
	 *
	 * @param string $subject	original string
	 * @return string			normalized string
	 */
	
	public static function normalize($subject)
	{
		// this is influenced by the setlocale() call with category LC_CTYPE; see PopulateDatabases.php
		
		$normalized = iconv( 'UTF-8', 'ASCII//TRANSLIT', $subject ); 
		$normalized = Xerxes_Framework_Parser::strtolower( $normalized );
		
		$normalized = str_replace( "&amp;", "", $normalized );
		$normalized = str_replace( "'", "", $normalized );
		$normalized = str_replace( "+", "-", $normalized );
		$normalized = str_replace( " ", "-", $normalized );
		
		$normalized = Xerxes_Framework_Parser::preg_replace( '/\W/', "-", $normalized );
		
		while ( strstr( $normalized, "--" ) )
		{
			$normalized = str_replace( "--", "-", $normalized );
		}
		
		return $normalized;
	}

	public function toXML()
	{
		$xml = new DOMDocument();
		$xml->loadXML("<category />");
		$xml->documentElement->setAttribute("name", $this->name);
		$xml->documentElement->setAttribute("normalized", $this->normalized);
		
		foreach ( $this->subcategories as $subcategory )
		{
			$import = $xml->importNode($subcategory->toXML()->documentElement, true);
			$xml->documentElement->appendChild($import);
		}
		
		return $xml;
	}
}
