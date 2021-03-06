<?php

/*
 * This file is part of Xerxes.
 *
 * (c) California State University <library@calstate.edu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Application\Controller;

use Application\Model\Solr\Engine;
use Xerxes\Mvc\Request;

class SolrController extends SearchController
{
	protected $id = "solr";
	
	protected function getEngine()
	{
		return new Engine();
	}
	
	public function smsAction()
	{
		$id = $this->request->getParam('id');
		$item_no = (int) $this->request->getParam('item');
		
		$phone = $this->request->requireParam('phone', 'Please enter a phone number');
		$provider = $this->request->requireParam('provider', 'Please choose your cell phone provider');

		// save provider in session
			
		$this->request->setSessionData("user_provider", $provider);
			
		// position is one-based in XSLT so switch to zero-based here
		
		$item_no = $item_no - 1;
		
		// record
	
		$results = $this->engine->getRecord($id);	
		$result = $results->getRecord(0);
		
		// send it
		
		$success = $result->textLocationTo($phone, $provider, $item_no);
		
		// flash
		
		if ( $success == true )
		{
			$this->request->setFlashMessage(Request::FLASH_MESSAGE_NOTICE, 'Message successfully sent');
		}
		else
		{
			$this->request->setFlashMessage(Request::FLASH_MESSAGE_ERROR, "Sorry, we couldn't send a message at this time");
		}
		
		// send back to main record page
		
		$params = array(
			'controller' => $this->id,
			'action' => 'record',
			'id' => $id,
			// 'format' => 'xerxes'
		);
		
		return $this->redirectTo($params);
	}
}
