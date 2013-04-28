<?php
/**
 *
 * @author      Knut Kohl <github@knutkohl.de>
 * @copyright   2012-2013 Knut Kohl
 * @license     GNU General Public License http://www.gnu.org/licenses/gpl.txt
 * @version     $Id$
 */
class ControllerAuth extends Controller {

	/**
	 *
	 */
	public function before() {

		if ($this->config->Admin_User == '' AND
		    $this->router->Route != 'adminpass') {
			$this->redirect('adminpass');
		}

		if ($this->router->Route != 'login') {

			if (Session::get('user') == $this->config->Admin_User) {
				// Ok, we have a validated user session
				$this->User = Session::get('user');
			} elseif (isset($_COOKIE[Session::token()])) {
				// Ok, we have a remembered user
				Session::set('user', $this->config->Admin_User);
				$this->User = $this->config->Admin_User;
			} else {
				// Login!
				Session::set('returnto', $this->router->Route);
				$this->redirect('login');
			}
		}

		parent::before();
	}

	/**
	 *
	 */
	public function after() {

		if ($this->User == '') {
			unset($_COOKIE[Session::token()]);
			Session::regenerate();
			Session::set('user');
		} else {
			if (isset($_SESSION['returnto'])) {
				$r = $_SESSION['returnto'];
				unset($_SESSION['returnto']);
				$this->redirect($r);
			}
		}
		$this->view->User = $this->User;

		parent::after();
	}

	// -------------------------------------------------------------------------
	// PROTECTED
	// -------------------------------------------------------------------------

	/**
	 *
	 */
	protected $User;

}
