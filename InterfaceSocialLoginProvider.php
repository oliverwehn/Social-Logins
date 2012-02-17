<?php
/**
 * Interface for SocialLogin Processes
 */

interface InterfaceSocialLoginProvider {
	
	public function getConfigFields();
	
	public function getUserRoles();
	
	public function extendLoginForm(&$form);
	
}