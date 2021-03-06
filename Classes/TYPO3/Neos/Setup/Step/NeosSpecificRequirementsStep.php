<?php
namespace TYPO3\Neos\Setup\Step;

/*                                                                        *
 * This script belongs to the Flow package "TYPO3.Neos".                  *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Configuration\ConfigurationManager;
use TYPO3\Flow\Package\PackageManagerInterface;
use TYPO3\Flow\Persistence\PersistenceManagerInterface;
use TYPO3\Flow\Resource\ResourceManager;
use TYPO3\Flow\Utility\Arrays;
use TYPO3\Flow\Utility\Files;

/**
 * @Flow\Scope("singleton")
 */
class NeosSpecificRequirementsStep extends \TYPO3\Setup\Step\AbstractStep {

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Configuration\Source\YamlSource
	 */
	protected $configurationSource;

	/**
	 * @Flow\Inject
	 * @var ResourceManager
	 */
	protected $resourceManager;

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Imagine\ImagineFactory
	 */
	protected $imagineFactory;

	/**
	 * @Flow\Inject
	 * @var PackageManagerInterface
	 */
	protected $packageManager;

	/**
	 * @Flow\Inject
	 * @var PersistenceManagerInterface
	 */
	protected $persistenceManager;

	/**
	 * {@inheritdoc}
	 */
	protected function buildForm(\TYPO3\Form\Core\Model\FormDefinition $formDefinition) {
		$page1 = $formDefinition->createPage('page1');
		$page1->setRenderingOption('header', 'Neos requirements check');

		$imageSection = $page1->createElement('connectionSection', 'TYPO3.Form:Section');
		$imageSection->setLabel('Image Manipulation');

		$foundImageHandler = FALSE;
		foreach (array('gd', 'gmagick', 'imagick') as $extensionName) {
			$formElement = $imageSection->createElement($extensionName, 'TYPO3.Form:StaticText');

			if (extension_loaded($extensionName)) {
				$unsupportedFormats = $this->findUnsupportedImageFormats($extensionName);
				if(count($unsupportedFormats) === 0) {
					$formElement->setProperty('text', 'PHP extension "' . $extensionName .'" is installed');
					$formElement->setProperty('elementClassAttribute', 'alert alert-info');
					$foundImageHandler = $extensionName;
				} else {
					$formElement->setProperty('text', 'PHP extension "' . $extensionName . '" is installed but lacks support for ' . implode(', ', $unsupportedFormats));
					$formElement->setProperty('elementClassAttribute', 'alert alert-warning');
				}
			} else {
				$formElement->setProperty('text', 'PHP extension "' . $extensionName . '" is not installed');
				$formElement->setProperty('elementClassAttribute', 'alert alert-warning');
			}
		}

		if ($foundImageHandler === FALSE) {
			$formElement = $imageSection->createElement('noImageLibrary', 'TYPO3.Form:StaticText');
			$formElement->setProperty('text', 'No suitable PHP extension for image manipulation was found. You can continue the setup but be aware that Neos might not work correctly without one of these extensions.');
			$formElement->setProperty('elementClassAttribute', 'alert alert-error');
		} else {
			$formElement = $imageSection->createElement('configuredImageLibrary', 'TYPO3.Form:StaticText');
			$formElement->setProperty('text', 'Neos will be configured to use extension "' . $foundImageHandler . '"');
			$formElement->setProperty('elementClassAttribute', 'alert alert-success');
			$hiddenField = $imageSection->createElement('imagineDriver', 'TYPO3.Form:HiddenField');
			$hiddenField->setDefaultValue(ucfirst($foundImageHandler));
		}

		$webServerSection = $page1->createElement('resourceSection', 'TYPO3.Form:Section');
		$webServerSection->setLabel('Web Server Configuration');
		if ($this->canResourceBeUploadedAndFetchedAgain()) {
			$formElement = $webServerSection->createElement('resources', 'TYPO3.Form:StaticText');
			$formElement->setProperty('text', 'File Uploads are configured as expected.');
			$formElement->setProperty('elementClassAttribute', 'alert alert-success');
		} else {
			$formElement = $webServerSection->createElement('resources', 'TYPO3.Form:StaticText');
			$formElement->setProperty('text', "File Uploads not configured correctly. This can have the following reasons:\n\n(1) The web server does not have permissions to read files in Web/_Resources. This might occur if a parent directory is not readable.\n(2) If you upgraded from Flow 2.x or Neos 1.x, you need to adjust your Web Server Configuration and remove the rewrite rule for _Resources/Persistent. See the release notes for details.");
			$formElement->setProperty('elementClassAttribute', 'alert alert-warning');
		}
	}

	/**
	 * Try to check whether resource publishing works, and the server is configured correctly or not.
	 *
	 * We need to do this "manually" without accessing the Resource Manager directly, as we need to avoid any
	 * database calls (as the database might be gone at this point).
	 *
	 * @return boolean TRUE if resource publishing worked; FALSE otherwise.
	 * @throws \TYPO3\Flow\Resource\Exception
	 */
	protected function canResourceBeUploadedAndFetchedAgain() {
		$collection = $this->resourceManager->getCollection(ResourceManager::DEFAULT_PERSISTENT_COLLECTION_NAME);
		$resource = $collection->importResourceFromContent('example-upload-test');
		$resource->setFilename('example-upload-test.txt');
		$publicResourceUri = $this->resourceManager->getPublicPersistentResourceUri($resource);
		$collection->getTarget()->publishResource($resource, $collection);

		$result = @file_get_contents($publicResourceUri);

		return $result === 'example-upload-test';
	}

	/**
	 * @param string $driver
	 * @return array Not supported image format
	 */
	protected function findUnsupportedImageFormats($driver) {
		$this->imagineFactory->injectSettings(array('driver' => ucfirst($driver)));
		$imagine = $this->imagineFactory->create();
		$unsupportedFormats = array();

		foreach(array('jpg', 'gif', 'png') as $imageFormat) {
			$imagePath = Files::concatenatePaths(array($this->packageManager->getPackage('TYPO3.Neos')->getResourcesPath(), 'Private/Installer/TestImages/Test.' . $imageFormat));

			try {
				$imagine->open($imagePath);
			} catch (\Exception $exception) {
				$unsupportedFormats[] = sprintf('"%s"', $imageFormat);
			}
		}

		return $unsupportedFormats;
	}

	/**
	 * {@inheritdoc}
	 */
	public function postProcessFormValues(array $formValues) {
		$this->distributionSettings = Arrays::setValueByPath($this->distributionSettings, 'TYPO3.Imagine.driver', $formValues['imagineDriver']);
		$this->configurationSource->save(FLOW_PATH_CONFIGURATION . ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, $this->distributionSettings);

		$this->configurationManager->flushConfigurationCache();
	}

}