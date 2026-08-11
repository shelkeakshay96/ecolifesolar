<?php

declare(strict_types=1);

namespace EcoLife\Testimonial\Controller\Adminhtml\Testimonial;

use EcoLife\Backend\App\Action\AbstractAction;
use EcoLife\Core\App\Logger;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Core\Model\ImageUploader;
use EcoLife\Testimonial\Model\Testimonial;
use RuntimeException;
use Throwable;

/**
 * Insert and update, one controller, as Gallery\...\Item\Save does.
 *
 * The `use` above is load bearing. This class sits inside the namespace
 * EcoLife\Testimonial\Controller\Adminhtml\Testimonial, so without the import
 * `new Testimonial()` resolves to this directory rather than to the model --
 * a fatal at runtime, not a parse error.
 */
final class Save extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        $messages = $this->context->getMessages();
        $back     = $this->context->getUrl()->getUrl('testimonials');

        if (!$this->getRequest()->isPost()) {
            return $this->resultRedirect()->setUrl($back);
        }

        $request     = $this->getRequest();
        $id          = (int) $request->getPost('testimonial_id', 0);
        $testimonial = $id > 0 ? (new Testimonial())->load($id) : new Testimonial();

        if ($id > 0 && $testimonial->getId() === null) {
            $messages->error('That testimonial no longer exists.');
            return $this->resultRedirect()->setUrl($back);
        }

        $name  = trim((string) $request->getPost('customer_name', ''));
        $quote = trim((string) $request->getPost('quote', ''));

        if ($name === '') {
            $messages->error('Please enter the customer\'s name.');
            return $this->resultRedirect()->setUrl($back);
        }

        if ($quote === '') {
            $messages->error('Please enter what the customer said.');
            return $this->resultRedirect()->setUrl($back);
        }

        $language = (string) $request->getPost('language', 'en');

        $testimonial->addData([
            'customer_name'  => mb_substr($name, 0, 120),
            'location'       => mb_substr(trim((string) $request->getPost('location', '')), 0, 100) ?: null,
            'business'       => mb_substr(trim((string) $request->getPost('business', '')), 0, 150) ?: null,
            'quote'          => mb_substr($quote, 0, 65535),
            'system_size_kw' => is_numeric($request->getPost('system_size_kw'))
                                    ? round((float) $request->getPost('system_size_kw'), 2) : null,
            'language'       => array_key_exists($language, Testimonial::LANGUAGES) ? $language : 'en',
            'sort_order'     => (int) $request->getPost('sort_order', 0),
            'is_active'      => $request->getPost('is_active') ? 1 : 0,
        ]);

        // A testimonial is worth publishing with or without a face, so unlike
        // the gallery there is no "you must choose a photo" branch. Only an
        // upload that was actually attempted and then failed is an error.
        $upload = $request->getFiles('photo');
        $hasNew = is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($hasNew) {
            try {
                // 640px and no thumbnail: this is only ever drawn as a small
                // round avatar, so a second file would be one nothing reads.
                $paths = (new ImageUploader('testimonial', 640, null))->upload($upload);
            } catch (RuntimeException $e) {
                $messages->error($e->getMessage());
                return $this->resultRedirect()->setUrl($back);
            } catch (Throwable $e) {
                Logger::exception($e);
                $messages->error('That photo could not be processed.');
                return $this->resultRedirect()->setUrl($back);
            }

            $superseded = $testimonial->getPhotoPath();
            $testimonial->setData('photo_path', $paths['image_path']);
        } elseif ($request->getPost('remove_photo')) {
            $superseded = $testimonial->getPhotoPath();
            $testimonial->setData('photo_path', null);
        }

        try {
            $testimonial->save();
        } catch (Throwable $e) {
            Logger::exception($e);
            $messages->error('Could not save that testimonial.');
            return $this->resultRedirect()->setUrl($back);
        }

        // Only once the row is safely saved is the old file removed. The other
        // order loses the photograph if the save then fails.
        if (isset($superseded) && $superseded !== '') {
            (new ImageUploader('testimonial'))->delete($superseded);
        }

        Logger::info('Testimonial saved', [
            'id' => (int) $testimonial->getId(),
            'by' => $this->getCurrentUser()?->getUsername(),
        ]);

        $messages->success($id > 0 ? 'Testimonial updated.' : 'Testimonial added.');

        return $this->resultRedirect()->setUrl($back);
    }
}
