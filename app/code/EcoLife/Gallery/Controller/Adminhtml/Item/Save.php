<?php

declare(strict_types=1);

namespace EcoLife\Gallery\Controller\Adminhtml\Item;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Core\App\Logger;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Core\Model\ImageUploader;
use EcoLife\Gallery\Model\Item;
use RuntimeException;
use Throwable;

final class Save extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        $messages = $this->context->getMessages();
        $back     = $this->context->getUrl()->getUrl('gallery');

        if (!$this->getRequest()->isPost()) {
            return $this->resultRedirect()->setUrl($back);
        }

        $request = $this->getRequest();
        $id      = (int) $request->getPost('item_id', 0);
        $item    = $id > 0 ? (new Item())->load($id) : new Item();

        if ($id > 0 && $item->getId() === null) {
            $messages->error('That gallery item no longer exists.');
            return $this->resultRedirect()->setUrl($back);
        }

        $title = trim((string) $request->getPost('title', ''));

        if ($title === '') {
            $messages->error('Please give the photo a title.');
            return $this->resultRedirect()->setUrl($back);
        }

        $category = (string) $request->getPost('category', 'residential');

        $item->addData([
            'title'          => mb_substr($title, 0, 190),
            'description'    => trim((string) $request->getPost('description', '')) ?: null,
            'location'       => mb_substr(trim((string) $request->getPost('location', '')), 0, 100) ?: null,
            'category'       => array_key_exists($category, Item::CATEGORIES) ? $category : 'residential',
            'system_size_kw' => is_numeric($request->getPost('system_size_kw'))
                                    ? round((float) $request->getPost('system_size_kw'), 2) : null,
            'install_date'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->getPost('install_date', ''))
                                    ? (string) $request->getPost('install_date') : null,
            'sort_order'     => (int) $request->getPost('sort_order', 0),
            'is_active'      => $request->getPost('is_active') ? 1 : 0,
        ]);

        $upload = $request->getFiles('image');
        $hasNewImage = is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($hasNewImage) {
            try {
                $paths = (new ImageUploader())->upload($upload);
            } catch (RuntimeException $e) {
                $messages->error($e->getMessage());
                return $this->resultRedirect()->setUrl($back);
            } catch (Throwable $e) {
                Logger::exception($e);
                $messages->error('That image could not be processed.');
                return $this->resultRedirect()->setUrl($back);
            }

            $old = [$item->getData('image_path'), $item->getData('thumbnail_path')];
            $item->addData($paths);
        } elseif ($item->getImagePath() === '') {
            $messages->error('Please choose a photo to upload.');
            return $this->resultRedirect()->setUrl($back);
        }

        try {
            $item->save();
        } catch (Throwable $e) {
            Logger::exception($e);
            $messages->error('Could not save that gallery item.');
            return $this->resultRedirect()->setUrl($back);
        }

        // Only after the row is safely saved is the superseded file removed.
        if (isset($old)) {
            (new ImageUploader())->delete(...$old);
        }

        $messages->success($id > 0 ? 'Gallery item updated.' : 'Photo added to the gallery.');

        return $this->resultRedirect()->setUrl($back);
    }
}
