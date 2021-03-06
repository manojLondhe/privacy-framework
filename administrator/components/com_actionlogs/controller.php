<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_actionlogs
 *
 * @copyright   Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Actionlogs Controller
 *
 * @since  __DEPLOY_VERSION__
 */
class ActionlogsController extends JControllerLegacy
{
	/**
	 * Method to display a view.
	 *
	 * @param   boolean  $cachable   If true, the view output will be cached
	 * @param   array    $urlparams  An array of safe URL parameters and their variable types, for valid values see {@link JFilterInput::clean()}.
	 *
	 * @return  ActionlogsController  This object to support chaining.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function display($cachable = false, $urlparams = array())
	{
		$view = $this->input->get('view', 'actionlogs');

		switch ($view)
		{
			case 'actionlogs':
				if (!JFactory::getUser()->authorise('core.viewlogs', 'com_actionlogs'))
				{
					throw new JAccessExceptionNotallowed(JText::_('JERROR_ALERTNOAUTHOR'), 403);
				}
				break;
		}

		return parent::display();
	}
}
