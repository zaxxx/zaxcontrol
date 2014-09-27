<?php

namespace Zax\Application\UI;
use Nette,
	Nette\Utils\Strings,
	Zax;

/**
 * Class Control
 *
 * Greatly enhances Nette Control.
 * - no need to manually redraw snippets
 * - no need to manually specify paths to templates
 * - no need to manually check "if is ajax" everywhere...
 */
abstract class Control extends Nette\Application\UI\Control {

	/**
	 * Current view, accessible via URL
	 *
	 * @persistent
	 */
	public $view = 'Default';

	/**
	 * Is AJAX currently enabled?
	 *
	 * @var bool
	 */
	protected $ajaxEnabled = FALSE;

	/**
	 * Should snippets be sent automatically, without the need to call redrawControl()?
	 *
	 * @var bool
	 */
	protected $sendSnippets = FALSE;

	/**
	 * Names of sub-components that should be excluded from automatic AJAXification
	 *
	 * @var array
	 */
	protected $ajaxDisabledFor = [];

	/**
	 * Enables AJAX for this component AND sub-components
	 *
	 * @param bool $autoAjax Should call redrawControl() in attached()?
	 * @param array $exclude array of subcomponent names which should be excluded from AJAXification
	 * @return $this
	 */
	public function enableAjax($autoAjax = TRUE, $exclude = NULL) {
		$this->ajaxEnabled = TRUE;
		$this->sendSnippets = $autoAjax;
		if(is_array($exclude)) {
			$this->disableAjaxFor($exclude);
		} else if(is_string($exclude)) {
			$this->disableAjaxFor([$exclude]);
		}
		return $this;
	}

	/**
	 * Forces AJAX off on specified subcomponents (has higher priority than enableAjax())
	 *
	 * @param array $subcomponents array of subcomponent names which should be excluded from AJAXification
	 * @return $this
	 */
	public function disableAjaxFor(array $subcomponents = []) {
		$this->ajaxDisabledFor = $subcomponents;
		return $this;
	}

	/**
	 * Disables AJAX for this component.
	 * Do not call in factory, it won't work, use disableAjaxFor in parent component instead ;-)
	 *
	 * @return $this
	 */
	public function disableAjax() {
		$this->ajaxEnabled = FALSE;
		$this->sendSnippets = FALSE;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function isAjaxEnabled() {
		return $this->ajaxEnabled;
	}

	/**
	 * Does this control have a persistent property $property?
	 *
	 * @param $property
	 * @return bool
	 */
	public function hasPersistentProperty($property) {
		$ref = $this->getReflection();
		if($ref->hasProperty($property)) {
			$refp = $ref->getProperty($property);
			return $refp->isPublic() && $refp->hasAnnotation('persistent');
		}
		return FALSE;
	}

	/**
	 * Forward using $presenter->forward()
	 *
	 * @param       $destination
	 * @param array $args
	 */
	public function presenterForward($destination, $args = []) {
		$name = $this->getUniqueId();
		if($destination != 'this') {
			$destination =  "$name-$destination";
		}
		$params = [];
		foreach($args as $key => $val) {
			$params["$name-$key"] = $val;
		}
		$this->presenter->forward($destination, $params);
	}

	/**
	 * Custom forward
	 *
	 * @param       $destination
	 * @param array $args
	 */
	public function forward($destination, $args = []) {

		// Remove '!' from destination
		$destination = str_replace('!', '', $destination);

		// Remove anchor from destination and insert anchor to payload
		$anchor = strpos($destination, '#');
		if(is_int($anchor)) {
			list($destination, $anchor) = explode('#', $destination);
			if($this->ajaxEnabled && $this->presenter->isAjax()) {
				$this->presenter->payload->anchor = $anchor;
			}
		}

		// Process arguments
		$params = [];
		foreach($args as $key =>$param) {
			$control = $this;
			$property = $key;

			// Get subcomponent from name
			if(strpos($key, self::NAME_SEPARATOR) > 0) {
				$names = explode(self::NAME_SEPARATOR, $key);
				$property = array_pop($names);
				$control = $this->getComponent(implode(self::NAME_SEPARATOR, $names));
			}

			if(strtolower($property) === 'view') {
				$control->setView($param);
			}else if($control->hasPersistentProperty($property)) {
				$control->$property = $param;
			} else {
				$params[$key] = $param;
			}
		}
		$this->params = $params;

		// No signal
		if(strtolower($destination) === 'this')
			return;

		$this->signalReceived($destination);
	}

	/**
	 * If AJAX, then forward, else redirect
	 *
	 * @param       $destination
	 * @param array $args
	 * @param array $snippets
	 * @param bool  $presenterForward Prefer $presenter->forward() over $this->forward()
	 *                                Not that $presenter->forward() breaks sending snippets
	 */
	final public function go($destination, $args = [], $snippets = [], $presenterForward = FALSE) {
		if($this->ajaxEnabled && $this->presenter->isAjax()) {
			foreach($snippets as $snippet) {
				$this->redrawControl($snippet);
			}

			if($presenterForward) {
				$this->presenterForward($destination, $args);
			} else {
				$this->forward($destination, $args);
			}
		} else {
			$this->redirect($destination, $args);
		}
	}

	/**
	 * Returns method name from view. Can be overriden for customization.
	 *
	 * @param $view
	 * @return string
	 */
	static public function formatViewMethod($view) {
		return 'view' . Strings::firstUpper($view);
	}

	/**
	 * Returns render method name. Can be overriden for customization.
	 *
	 * @param $render
	 * @return string
	 */
	static public function formatBeforeRenderMethod($render) {
		return 'beforeRender' . Strings::firstUpper($render);
	}

	/**
	 * @param $view
	 */
	public function setView($view) {
		$this->view = Strings::firstUpper($view);
	}

	/**
	 * Validates view name,
	 * throws exception if view name contains anything else than alphanumeric characters.
	 *
	 * @param $view
	 * @throws Nette\Application\UI\BadSignalException
	 */
	protected function checkView($view) {
		if(!preg_match('/^([a-zA-Z0-9]+)$/', $view)) {
			throw new Nette\Application\UI\BadSignalException("Signal or view name must be alphanumeric.");
		}
	}

	/**
	 * Automatic snippet invalidation
	 */
	public function attached($presenter) {
		parent::attached($presenter);
		if(!$presenter instanceof Nette\Application\UI\Presenter) {
			return;
		}
		if($this->sendSnippets && $this->ajaxEnabled && $presenter->isAjax()) {
			$this->redrawControl();
		}
	}

	/**
	 * Iterates over all components and suppresses their redrawing. It does not disable AJAX for them, it's
	 * only intended for better control over snippets.
	 */
	public function redrawNothing() {
		foreach($this->getPresenter()->getComponents(TRUE, 'Nette\Application\UI\IRenderable') as $component) {
			/** @var Nette\Application\UI\IRenderable $component */
			$component->redrawControl(NULL, FALSE);
		}
	}

	/**
	 * Descendants can override this method to customize templates hierarchy
	 *
	 * Default implementation uses './templates/<View>[.<Render>].latte' pattern. If the template doesn't exist, it
	 * will search for it in parent class. This allows you to inherit a component and reuse or customize its templates
	 * freely.
	 *
	 * @param $view
	 * @param $render
	 * @return string
	 */
	public function getTemplatePath($view, $render = '') {
		$class = $this->reflection;
		do {
			$path = dirname($class->fileName)
				. DIRECTORY_SEPARATOR
				. 'templates'
				. DIRECTORY_SEPARATOR
				. $view
				. (strlen($render) > 0 ? '.' . $render : '')
				. '.latte';
			if(is_file($path)) {
				return $path;
			}
			$class = $class->getParentClass();
		} while (TRUE);
	}

	/**
	 * Checks parameter $view for malicious input before creating a template
	 *
	 * @return Nette\Application\UI\ITemplate
	 * @throws Nette\Application\UI\BadSignalException
	 */
	public function createTemplate() {
		$this->checkView($this->view);
		$template = parent::createTemplate();
		return $template;
	}

	/**
	 * Enables AJAX on a sub-component. Can be overriden for customization.
	 *
	 * @param $name
	 * @param $control
	 */
	protected function prepareComponent($name, $control) {
		if($this->ajaxEnabled && !in_array($name, $this->ajaxDisabledFor) && $control instanceof Control) {
			$control->enableAjax();
		}
	}

	/**
	 * @param $name
	 * @return Nette\ComponentModel\IComponent
	 */
	protected function createComponent($name) {
		$control = parent::createComponent($name);
		$this->prepareComponent($name, $control);
		return $control;
	}

	/**
	 * Replacement for render(), descendants should override this method
	 */
	protected function beforeRender() {}

	/**
	 * Life cycle
	 *
	 * @param string $render
	 * @throws \Nette\Application\UI\BadSignalException
	 */
	final public function run($render = '') {
		$template = $this->getTemplate();

		if(!$this->tryCall($this->formatViewMethod($this->view), $this->params)) {
			$class = get_class($this);
			throw new Nette\Application\UI\BadSignalException("There is no 'view$this->view' method in class $class.");
		}

		if(!$this->tryCall($this->formatBeforeRenderMethod($render), array())) {
			$class = get_class($this);
			throw new Nette\Application\UI\BadSignalException("There is no 'beforeRender$render' method in class $class.");
		}

		$template->setFile($this->getTemplatePath($this->view, $render));

		$template->render();
	}

	/**
	 * Hack render[<View>]() method calls
	 *
	 * @param       $func
	 * @param array $args
	 * @return mixed|void
	 */
	public function __call($func, $args = []) {
		if (Strings::startsWith($func, 'render')) {
			return $this->run(Strings::substring($func, 6));
		}
		return parent::__call($func, $args);
	}

}
