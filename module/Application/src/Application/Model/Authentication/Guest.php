<?php

namespace Application\Model\Authentication;

use Xerxes\Utility\User;

/**
 * Guest Authentication
 * 
 * @author David Walker
 * @copyright 2011 California State University
 * @version
 * @package Xerxes
 * @link http://xerxes.calstate.edu
 * @license http://www.gnu.org/licenses/
 */

class Guest extends Authentication 
{
	/**
	 * Just register the user with a role of guest
	 */
	
	public function onLogin() 
	{
		$this->user->username = User::genRandomUsername(User::GUEST);
		$this->user->role = User::GUEST;
		
		return $this->register();
	}
}
