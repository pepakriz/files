<?php

/**
 * This file is part of the Venne:CMS (https://github.com/Venne)
 *
 * Copyright (c) 2011, 2012 Josef Kříž (http://www.josef-kriz.cz)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace Venne\Files\AdminModule;

use Kdyby\Doctrine\EntityDao;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Utils\Image;
use Venne\Files\PermissionDeniedException;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class FilePresenter extends \Nette\Application\UI\Presenter
{

	/** @var string */
	public $size;

	/** @var string */
	public $format;

	/** @var string */
	public $type;

	/** @var string */
	public $url;

	/** @var bool */
	protected $cached = false;

	/** @var \Kdyby\Doctrine\EntityDao */
	protected $fileDao;

	public function __construct(EntityDao $fileDao)
	{
		$this->fileDao = $fileDao;
		$this->autoCanonicalize = false;
	}

	protected function startup()
	{
		parent::startup();

		$this->size = $this->getParameter('size');
		$this->format = $this->getParameter('format');
		$this->type = $this->getParameter('type');
		$this->url = $this->getParameter('url');
	}

	public function actionDefault()
	{
		if (!($file = $this->fileDao->findOneBy(array('path' => $this->url)))) {
			throw new BadRequestException;
		}

		try {
			$file = $file->getFilePath();

			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mime_type = finfo_file($finfo, $file);
			finfo_close($finfo);

			header('Content-type: ' . $mime_type);
			echo file_get_contents($file);
			$this->terminate();
		} catch (PermissionDeniedException $e) {
			throw new ForbiddenRequestException;
		}
	}

	public function actionImage()
	{
		if (substr($this->url, 0, 7) === '_cache/') {
			$this->cached = true;
			$this->url = substr($this->url, 7);
		}

		if (($entity = $this->fileDao->findOneBy(array('path' => $this->url))) === null) {
			throw new \Nette\Application\BadRequestException("File '{$this->url}' does not exist.");
		}

		$image = Image::fromFile($entity->getFilePath());

		// resize
		if ($this->size && $this->size !== 'default') {
			if (strpos($this->size, 'x') !== false) {
				$format = explode('x', $this->size);
				$width = $format[0] !== '?' ? $format[0] : null;
				$height = $format[1] !== '?' ? $format[1] : null;
				$image->resize($width, $height, $this->format !== 'default' ? $this->format : Image::FIT);
			}
		}

		if (!$this->type) {
			$this->type = substr($entity->getName(), strrpos($entity->getName(), '.'));
		}

		$type = $this->type === 'jpg' ? Image::JPEG : $this->type === 'gif' ? Image::GIF : Image::PNG;

		$file = $this->context->parameters['wwwDir'] . "/public/media/_cache/{$this->size}/{$this->format}/{$this->type}/{$entity->getPath()}";
		$dir = dirname($file);
		umask(0000);
		@mkdir($dir, 0777, true);
		$image->save($file, 90, $type);
		$image->send($type, 90);
	}

}

