<?php
/**
 * Implements Special:OpenIDDashboard parameter settings and status information
 *
 * @ingroup SpecialPage
 * @ingroup Extensions
 * @author Thomas Gries
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link http://www.mediawiki.org/wiki/Extension:OpenID Documentation
 *
 */
class SpecialOpenIDDashboard extends SpecialPage {

	/**
	 * Constructor - sets up the new special page
	 * required right: openid-dashboard-access
	 */
	public function __construct() {
		parent::__construct( 'OpenIDDashboard', 'openid-dashboard-access' );
	}

	/**
	 * Different description will be shown on Special:SpecialPage depending on
	 * whether the user has the 'openiddashboard' right or not.
	 */
	function getDescription() {
		global $wgUser;

		if ( $wgUser->isAllowed( 'openid-dashboard-admin' ) ) {
				return wfMessage( 'openid-dashboard-title-admin' )->text();
		} else {
				return wfMessage( 'openid-dashboard-title' )->text() ;
		}
	}

	/**
	 * @param $string string
	 * @param $value string
	 * @return string
	 */
	function show( $string, $value ) {
		if  ( $value === null ) {
			$value = 'null';
		} elseif ( is_bool( $value ) ) {
			$value = wfBoolToStr( $value );
		} else {
			$value = htmlspecialchars( $value );
		}
		return Html::openElement( 'tr' ) .
			Html::openElement( 'td' ) . $string . Html::closeElement( 'td' ) .
			Html::openElement( 'td' ) . $value . Html::closeElement( 'td' ) .
			Html::closeElement( 'tr' ) . "\n";
	}

	/**
	 * Show the special page
	 *
	 * @param $par Mixed: parameter passed to the page or null
	 */
	function execute( $par ) {

		global $wgOut, $wgUser;
		global $wgOpenIDShowUrlOnUserPage;
		global $wgOpenIDTrustEmailAddress;
		global $wgOpenIDAllowExistingAccountSelection;
		global $wgOpenIDAllowNewAccountname;
		global $wgOpenIDUseEmailAsNickname;
		global $wgOpenIDProposeUsernameFromSREG;
		global $wgOpenIDAllowAutomaticUsername;
		global $wgOpenIDLoginOnly;
		global $wgOpenIDConsumerAndAlsoProvider;
		global $wgOpenIDAllowServingOpenIDUserAccounts;
		global $wgOpenIDShowProviderIcons;

		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return;
		}

		$totalUsers = SiteStats::users();
		$OpenIDdistinctUsers = $this->getOpenIDUsers( 'distinctusers' );
		$OpenIDUsers = $this->getOpenIDUsers();

		$this->setHeaders();
		$this->outputHeader();

		$wgOut->addWikiMsg( 'openid-dashboard-introduction', 'http://www.mediawiki.org/wiki/Extension:OpenID' );

		$wgOut->addHTML(
			Html::openElement( 'table', array( 'style' => 'width:50%;', 'class' => 'mw-openiddashboard-table wikitable' ) )
		);

		# Here we show some basic version infos. Retrieval of SVN revision number of OpenID appears to be too difficult
		$out  = $this->show( 'OpenID ' . wfMessage( 'version-software-version' )->text(), MEDIAWIKI_OPENID_VERSION );
		$out .= $this->show( 'MediaWiki ' . wfMessage( 'version-software-version' )->text(), SpecialVersion::getVersion() );
		$out .= $this->show( '$wgOpenIDLoginOnly', $wgOpenIDLoginOnly );
		$out .= $this->show( '$wgOpenIDConsumerAndAlsoProvider', $wgOpenIDConsumerAndAlsoProvider );
		$out .= $this->show( '$wgOpenIDAllowServingOpenIDUserAccounts', $wgOpenIDAllowServingOpenIDUserAccounts );
		$out .= $this->show( '$wgOpenIDTrustEmailAddress', $wgOpenIDTrustEmailAddress );
		$out .= $this->show( '$wgOpenIDAllowExistingAccountSelection', $wgOpenIDAllowExistingAccountSelection );
		$out .= $this->show( '$wgOpenIDAllowAutomaticUsername', $wgOpenIDAllowAutomaticUsername );
		$out .= $this->show( '$wgOpenIDAllowNewAccountname', $wgOpenIDAllowNewAccountname );
		$out .= $this->show( '$wgOpenIDUseEmailAsNickname', $wgOpenIDUseEmailAsNickname );
		$out .= $this->show( '$wgOpenIDProposeUsernameFromSREG', $wgOpenIDProposeUsernameFromSREG );
		$out .= $this->show( '$wgOpenIDShowUrlOnUserPage', $wgOpenIDShowUrlOnUserPage );
		$out .= $this->show( '$wgOpenIDShowProviderIcons', $wgOpenIDShowProviderIcons );
		$out .= $this->show( wfMessage( 'statistics-users' )->parse(), $totalUsers );
		$out .= $this->show( wfMessage( 'openid-dashboard-number-openid-users' )->text(), $OpenIDdistinctUsers  );
		$out .= $this->show( wfMessage( 'openid-dashboard-number-openids-in-database' )->text(), $OpenIDUsers );
		$out .= $this->show( wfMessage( 'openid-dashboard-number-users-without-openid' )->text(), $totalUsers - $OpenIDdistinctUsers );

		$wgOut->addHTML( $out . Html::closeElement( 'table' ) . "\n" );

	}

	function error() {
		global $wgOut;
		$args = func_get_args();
		$wgOut->wrapWikiMsg( "<p class='error'>$1</p>", $args );
	}


	function getOpenIDUsers ( $distinctusers = '' ) {
		wfProfileIn( __METHOD__ );
		$distinct = ( $distinctusers == 'distinctusers' ) ? 'COUNT(DISTINCT uoi_user)' : 'COUNT(*)' ;

		$dbr = wfGetDB( DB_SLAVE );
		$OpenIDUserCount = (int)$dbr->selectField(
			'user_openid',
			$distinct,
			null,
			__METHOD__,
			null
		);
		wfProfileOut( __METHOD__ );
		return $OpenIDUserCount;
	}

}
