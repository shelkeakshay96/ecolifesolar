<?php

declare(strict_types=1);

namespace EcoLife\Gallery\Controller\Adminhtml\Item;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Core\App\Logger;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Gallery\Model\ImageUploader;
use EcoLife\Gallery\Model\Item;
use Throwable;

/** POST only, like every other destructive action. */
final class Delete extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        $messages = $this->context->getMessages();
        $back     = $this->context->getUrl()->getUrl('gallery');

        if (!$this->getRequest()->isPost()) {
            return $this->resultRedirect()->setUrl($back);
        }

        $item = (new Item())->load((int) $this->getRequest()->getParam('id', 0));

        if ($item->getId() === null) {
            $messages->error('That gallery item no longer exists.');
            return $this->resultRedirect()->setUrl($back);
        }

        $paths = [$item->getData('image_path'), $item->getData('thumbnail_path')];
        $title = $item->getTitle();

        try {
            $item->delete();
            (new ImageUploader())->delete(...$paths);
            $messages->success(sprintf('"%s" removed from the gallery.', $title));
        } catch (Throwable $e) {
            Logger::exception($e);
            $messages->error('Could not remove that item.');
        }

        return $this->resultRedirect()->setUrl($back);
    }
}
