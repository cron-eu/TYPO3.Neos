<?php
namespace TYPO3\Neos\ViewHelpers\Link;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "TYPO3.Neos".            *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;
use TYPO3\Neos\Exception as NeosException;
use TYPO3\Neos\Service\LinkingService;
use TYPO3\TypoScript\TypoScriptObjects\Helpers\TypoScriptAwareViewInterface;
use TYPO3\Fluid\Core\ViewHelper\Exception as ViewHelperException;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;

/**
 * A view helper for creating links with URIs pointing to nodes.
 *
 * The target node can be provided as string or as a Node object; if not specified
 * at all, the generated URI will refer to the current document node inside the TypoScript context.
 *
 * When specifying the ``node`` argument as string, the following conventions apply:
 *
 * *``node`` starts with ``/``:*
 * The given path is an absolute node path and is treated as such.
 * Example: ``/sites/acmecom/home/about/us``
 *
 * *``node`` does not start with ``/``:*
 * The given path is treated as a path relative to the current node.
 * Examples: given that the current node is ``/sites/acmecom/products/``,
 * ``stapler`` results in ``/sites/acmecom/products/stapler``,
 * ``../about`` results in ``/sites/acmecom/about/``,
 * ``./neos/info`` results in ``/sites/acmecom/products/neos/info``.
 *
 * *``node`` starts with a tilde character (``~``):*
 * The given path is treated as a path relative to the current site node.
 * Example: given that the current node is ``/sites/acmecom/products/``,
 * ``~/about/us`` results in ``/sites/acmecom/about/us``,
 * ``~`` results in ``/sites/acmecom``.
 *
 * = Examples =
 *
 * <code title="Defaults">
 * <neos:link.node>some link</neos:link.node>
 * </code>
 * <output>
 * <a href="sites/mysite.com/homepage/about.html">some link</a>
 * (depending on current node, format etc.)
 * </output>
 *
 * <code title="Generating a link with an absolute URI">
 * <neos:link.node absolute="{true"}>bookmark this page</neos:link.node>
 * </code>
 * <output>
 * <a href="http://www.example.org/homepage/about.html">bookmark this page</a>
 * (depending on current workspace, current node, format, host etc.)
 * </output>
 *
 * <code title="Target node given as absolute node path">
 * <neos:link.node node="/sites/exampleorg/contact/imprint">Corporate imprint</neos:link.node>
 * </code>
 * <output>
 * <a href="contact/imprint.html">Corporate imprint</a>
 * (depending on current workspace, current node, format etc.)
 * </output>
 *
 * <code title="Target node given as relative node path">
 * <neos:link.node node="~/about/us">About us</neos:link.node>
 * </code>
 * <output>
 * <a href="about/us.html">About us</a>
 * (depending on current workspace, current node, format etc.)
 * </output>
 * @api
 */
class NodeViewHelper extends AbstractTagBasedViewHelper {

	/**
	 * @var string
	 */
	protected $tagName = 'a';

	/**
	 * @Flow\Inject
	 * @var LinkingService
	 */
	protected $linkingService;

	/**
	 * Initialize arguments
	 *
	 * @return void
	 */
	public function initializeArguments() {
		$this->registerUniversalTagAttributes();
		$this->registerTagAttribute('name', 'string', 'Specifies the name of an anchor');
		$this->registerTagAttribute('rel', 'string', 'Specifies the relationship between the current document and the linked document');
		$this->registerTagAttribute('rev', 'string', 'Specifies the relationship between the linked document and the current document');
		$this->registerTagAttribute('target', 'string', 'Specifies where to open the linked document');
	}

	/**
	 * Renders the link.
	 *
	 * @param mixed $node A node object or a string node path or NULL to resolve the current document node
	 * @param string $format Format to use for the URL, for example "html" or "json"
	 * @param boolean $absolute If set, an absolute URI is rendered
	 * @param array $arguments Additional arguments to be passed to the UriBuilder (for example pagination parameters)
	 * @param string $section The anchor to be added to the URI
	 * @param boolean $addQueryString If set, the current query parameters will be kept in the URI
	 * @param array $argumentsToBeExcludedFromQueryString arguments to be removed from the URI. Only active if $addQueryString = TRUE
	 * @param string $baseNodeName The name of the base node inside the TypoScript context to use for the ContentContext or resolving relative paths
	 * @return string The rendered link
	 * @throws \TYPO3\Fluid\Core\ViewHelper\Exception
	 * @throws \TYPO3\Neos\Exception
	 */
	public function render($node = NULL, $format = NULL, $absolute = FALSE, array $arguments = array(), $section = '', $addQueryString = FALSE, array $argumentsToBeExcludedFromQueryString = array(), $baseNodeName = 'documentNode') {
		$baseNode = NULL;
		if (!$node instanceof NodeInterface) {
			$view = $this->viewHelperVariableContainer->getView();
			if (!$view instanceof TypoScriptAwareViewInterface) {
				throw new ViewHelperException('This ViewHelper can only be used in a TypoScript content element. You have to specify the "node" argument if it cannot be resolved from the TypoScript context.', 1385737102);
			}
			$typoScriptObject = $view->getTypoScriptObject();
			$currentContext = $typoScriptObject->getTsRuntime()->getCurrentContext();
			if (isset($currentContext[$baseNodeName])) {
				$baseNode = $currentContext[$baseNodeName];
			} else {
				throw new NeosException(sprintf('Could not find a node instance in TypoScript context with name "%s" and no node instance was given to the node argument. Set a node instance in the TypoScript context or pass a node object to resolve the URI.', $baseNodeName), 1373100400);
			}
		}
		$controllerContext = $this->controllerContext;
		$uri = $this->linkingService->createNodeUri(
			$controllerContext,
			$node,
			$baseNode,
			$format,
			$absolute,
			$arguments,
			$section,
			$addQueryString,
			$argumentsToBeExcludedFromQueryString
		);

		if ($uri !== NULL) {
			$this->tag->addAttribute('href', $uri);
		}

		$this->tag->setContent($this->renderChildren());
		return $this->tag->render();
	}
}
