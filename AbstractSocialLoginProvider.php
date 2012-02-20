<?php
/**
 * Abstract class definition for SocialLogin Processes
 */

abstract class AbstractSocialLoginProvider extends Process implements ConfigurableModule {
	
	/**
	 * Name of the provider
	 */
	protected $providerName;
	
	/**
	 * provide additional config fields besides roles for manager if needed
	 */
	abstract public function getConfigFields();
	
	/**
	 * Provide roles for users created within this login process
	 */	
	public function getUserRoles() {
		$roles = new PageArray();
		if(is_array($this->user_roles)) {
			foreach($this->user_roles as $user_role) {
				$roles->add($this->roles->get($user_role));
			}
		} else {
			$user_role = 0;
			if(($user_role = $this->roles->get($this->user_roles)) && ($user_role->id)) {
				$roles->add($user_role);
			}
		}
		return $roles;
	}
	
	/**
	 * provide fields or buttons for implementing provider in login form
	 */
	abstract public function extendLoginForm(&$form);
	
	/**
	 * pass on 
	 */
	public static function getModuleConfigInputfields(array $data) {
		$fields = $this->getConfigFields();
		return $fields;
	}
	
	
}