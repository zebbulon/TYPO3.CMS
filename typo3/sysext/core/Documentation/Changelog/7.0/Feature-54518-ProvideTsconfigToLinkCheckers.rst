===================================================
Feature: #54518 - Provide TSconfig to link checkers
===================================================

Description
===========

The active TSconfig of the linkvalidator is stored in the LinkAnalyser and made
publicly available to the link checkers.

The TSconfig is read either from the currently active TSconfig in the Backend
when the linkvalidator is used in the info module or from the configuration
provided in the linkvalidator scheduler task.

This allows passing configuration to the different link checkers.


Usage:

.. code-block:: typoscript

	# The configuration in mod.linkvalidator can be read by the link checkers.
	mod.linkvalidator.mychecker.myvar = 1

..

Impact
======

The method signature of `\TYPO3\CMS\Linkvalidator::LinkAnalyser::init()` is changed. A new paramter has been added
for submitting the current TSconfig. This can break third party code that extends this method.
