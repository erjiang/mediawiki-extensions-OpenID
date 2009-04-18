<?php
/**
 * SpecialOpenIDLogin.body.php -- Consumer side of OpenID site
 * Copyright 2006,2007 Internet Brands (http://www.internetbrands.com/)
 * Copyright 2007,2008 Evan Prodromou <evan@prodromou.name>
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @author Evan Prodromou <evan@prodromou.name>
 * @addtogroup Extensions
 */

if (!defined('MEDIAWIKI'))
  exit(1);

require_once("Auth/OpenID/Consumer.php");

class SpecialOpenIDLogin extends SpecialOpenID {

	function SpecialOpenIDLogin() {
		SpecialPage::SpecialPage("OpenIDLogin");
	}

	function execute($par) {
		global $wgRequest, $wgUser, $wgOut, $wgScriptPath, $wgOpenIDShowProviderIcons;

		wfLoadExtensionMessages( 'OpenID' );

		$this->setHeaders();

		$oidScriptPath = $wgScriptPath . '/extensions/OpenID';

		$wgOut->addLink( array(
			'rel' => 'stylesheet',
			'type' => 'text/css',
			'media' => 'screen, projection',
			'href' => $oidScriptPath.($wgOpenIDShowProviderIcons ? '/skin/openid.css' : '/skin/openid-plain.css')
		));

		$wgOut->addScript('<script type="text/javascript" src="'.$oidScriptPath.'/skin/jquery-1.3.2.min.js"></script>'."\n");
		$wgOut->addScript('<script type="text/javascript" src="'.$oidScriptPath.'/skin/openid.js"></script>'."\n");

		if ($wgUser->getID() != 0) {
			$this->alreadyLoggedIn();
			return;
		}

		if ($wgRequest->getText('returnto')) {
			$this->setReturnTo($wgRequest->getText('returnto'));
		}

		$openid_url = $wgRequest->getText('openid_url');

		if (isset($openid_url) && strlen($openid_url) > 0) {
			$this->login($openid_url);
		} else {
			$this->loginForm();
		}
	}

	function loginForm() {
		global $wgOut, $wgUser, $wgOpenIDLoginLogoUrl, $wgOpenIDShowProviderIcons;
		$sk = $wgUser->getSkin();
		$instructions = wfMsgExt('openidlogininstructions', array('parse'));

		$formsHTML = '';

		$largeButtonsHTML = '<div id="openid_large_providers">';
		foreach (OpenIDProvider::getLargeProviders() as $provider) {
			$largeButtonsHTML .= $provider->getLargeButtonHTML();
			$formsHTML .= $provider->getLoginFormHTML();
		}
		$largeButtonsHTML .= '</div>';

		$smallButtonsHTML = '';
		if ($wgOpenIDShowProviderIcons) {
			$smallButtonsHTML .= '<div id="openid_small_providers_icons">';
			foreach (OpenIDProvider::getSmallProviders() as $provider) {
				$smallButtonsHTML .= $provider->getSmallButtonHTML(); 
				$formsHTML .= $provider->getLoginFormHTML();
			}
			$smallButtonsHTML .= '</div>';
		}
		else
		{
			$smallButtonsHTML .= '<div id="openid_small_providers_links">';
			$smallButtonsHTML .= '<ul class="openid_small_providers_block">';
			$small = OpenIDProvider::getSmallProviders();

			$i = 0;
			$break = true;
			foreach ($small as $provider) {
				if ($break && $i > count($small)/2) {
					$smallButtonsHTML .= '</ul><ul class="openid_small_providers_block">';
					$break = false;
				}

				$smallButtonsHTML .= '<li>'.$provider->getSmallButtonHTML().'</li>';

				$formsHTML .= $provider->getLoginFormHTML();
				$i++;
			}
			$smallButtonsHTML .= '</ul>';
			$smallButtonsHTML .= '</div>';
		}

		$wgOut->addHTML('<form id="openid_form" action="' . $sk->makeSpecialUrl('OpenIDLogin') . '" method="POST" onsubmit="openid.update()"">' .
						'<fieldset><legend>' . wfMsg('openidsigninorcreateaccount') . '</legend>' .
						$largeButtonsHTML .
						'<div id="openid_input_area">' .
						$formsHTML .
						'</div>' . 
						$smallButtonsHTML .
						'</fieldset></form>' .
						$instructions
						);
	}

	function toUserName($openid) {
        if (Services_Yadis_identifierScheme($openid) == 'XRI') {
			wfDebug("OpenID: Handling an XRI: $openid\n");
			return $this->toUserNameXri($openid);
		} else {
			wfDebug("OpenID: Handling an URL: $openid\n");
			return $this->toUserNameUrl($openid);
		}
	}

	function alreadyLoggedIn() {

		global $wgUser, $wgOut;

		$wgOut->setPageTitle( wfMsg( 'openiderror' ) );
		$wgOut->setRobotPolicy( 'noindex,nofollow' );
		$wgOut->setArticleRelated( false );
		$wgOut->addWikiText( wfMsg( 'openidalreadyloggedin', $wgUser->getName() ) );
		$wgOut->returnToMain(false, $this->returnTo() );
	}

	function setUserUrl($user, $url) {
		$other = $this->getUserUrl($user);
		if (isset($other)) {
			$this->updateUserUrl($user, $url);
		} else {
			$this->insertUserUrl($user, $url);
		}
	}

	function insertUserUrl($user, $url) {
		global $wgSharedDB, $wgDBname;
		$dbw =& wfGetDB( DB_MASTER );

		if (isset($wgSharedDB)) {
			# It would be nicer to get the existing dbname
			# and save it, but it's not possible
			$dbw->selectDB($wgSharedDB);
		}

		$dbw->insert('user_openid', array('uoi_user' => $user->getId(),
										  'uoi_openid' => $url));

		if (isset($wgSharedDB)) {
			$dbw->selectDB($wgDBname);
		}
	}

	function updateUserUrl($user, $url) {
		global $wgSharedDB, $wgDBname;
		$dbw =& wfGetDB( DB_MASTER );

		if (isset($wgSharedDB)) {
			# It would be nicer to get the existing dbname
			# and save it, but it's not possible
			$dbw->selectDB($wgSharedDB);
		}

		$dbw->set('user_openid', 'uoi_openid', $url,
				  'uoi_user = ' . $user->getID());

		if (isset($wgSharedDB)) {
			$dbw->selectDB($wgDBname);
		}
	}

	function saveValues($response, $sreg) {
		global $wgSessionStarted, $wgUser;

		if (!$wgSessionStarted) {
			$wgUser->SetupSession();
		}

		$_SESSION['openid_consumer_response'] = $response;
		$_SESSION['openid_consumer_sreg'] = $sreg;

		return true;
	}

	function clearValues() {
		unset($_SESSION['openid_consumer_response']);
		unset($_SESSION['openid_consumer_sreg']);
		return true;
	}

	function fetchValues() {
		return array($_SESSION['openid_consumer_response'], $_SESSION['openid_consumer_sreg']);
	}

	function returnTo() {
		return $_SESSION['openid_consumer_returnto'];
	}

	function setReturnTo($returnto) {
		$_SESSION['openid_consumer_returnto'] = $returnto;
	}
}
