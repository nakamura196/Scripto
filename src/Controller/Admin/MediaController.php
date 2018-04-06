<?php
namespace Scripto\Controller\Admin;

use Scripto\Form\BatchMediaForm;
use Scripto\Form\MediaForm;
use Zend\View\Model\ViewModel;

class MediaController extends AbstractScriptoController
{
    public function browseAction()
    {
        $sItem = $this->getScriptoRepresentation(
            $this->params('project-id'),
            $this->params('item-id')
        );
        if (!$sItem) {
            return $this->redirect()->toRoute('admin/scripto-project');
        }

        $this->setBrowseDefaults('position', 'asc');
        $query = array_merge(
            ['scripto_item_id' => $sItem->id()],
            $this->params()->fromQuery()
        );
        $response = $this->api()->search('scripto_media', $query);
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));
        $sMedia = $response->getContent();

        $view = new ViewModel;
        $view->setVariable('sItem', $sItem);
        $view->setVariable('sMedia', $sMedia);
        $view->setVariable('project', $sItem->scriptoProject());
        $view->setVariable('item', $sItem->item());
        return $view;
    }

    public function showDetailsAction()
    {
        $sMedia = $this->getScriptoRepresentation(
            $this->params('project-id'),
            $this->params('item-id'),
            $this->params('media-id')
        );
        if (!$sMedia) {
            exit;
        }

        $sItem = $sMedia->scriptoItem();
        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('sMedia', $sMedia);
        $view->setVariable('media', $sMedia->media());
        $view->setVariable('sItem', $sItem);
        $view->setVariable('item', $sItem->item());
        $view->setVariable('project', $sItem->scriptoProject());
        return $view;
    }

    public function showAction()
    {
        $sMedia = $this->getScriptoRepresentation(
            $this->params('project-id'),
            $this->params('item-id'),
            $this->params('media-id')
        );
        if (!$sMedia) {
            return $this->redirect()->toRoute('admin/scripto-project');
        }

        $form = $this->getForm(MediaForm::class);
        $editAccess = $sMedia->editAccess();

        if ($this->getRequest()->isPost()) {
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $formData = $form->getData();

                // Update MediaWiki data.
                if ($sMedia->userCan('protect')) {
                    if ('' === $formData['protection_expiry']) {
                        // Use existing expiration date.
                        $protectionExpiry = $editAccess['expiry']
                            ? $editAccess['expiry']->format('c')
                            : 'infinite';
                    } else {
                        // Use selected expiration date.
                        $protectionExpiry = $formData['protection_expiry'];
                    }
                    $this->scriptoApiClient()->protectPage(
                        $sMedia->pageTitle(),
                        $sMedia->pageIsCreated() ? 'edit' : 'create',
                        $formData['protection_level'],
                        $protectionExpiry
                    );
                }
                if ($this->scriptoApiClient()->userIsLoggedIn()) {
                    if ($formData['is_watched']) {
                        $this->scriptoApiClient()->watchPage($sMedia->pageTitle());
                    } else {
                        $this->scriptoApiClient()->unwatchPage($sMedia->pageTitle());
                    }
                }

                // Update Scripto media.
                $data = [
                    'o-module-scripto:is_completed' => $formData['is_completed'],
                    'o-module-scripto:is_approved' => $formData['is_approved'],
                ];
                $response = $this->api($form)->update('scripto_media', $sMedia->id(), $data);
                if ($response) {
                    $this->messenger()->addSuccess('Scripto media successfully updated.'); // @translate
                    return $this->redirect()->toUrl($response->getContent()->url());
                }

            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        // Set form data.
        $data = [
            'is_completed' => (bool) $sMedia->completed(),
            'is_approved' => (bool) $sMedia->approved(),
            'is_watched' => $sMedia->isWatched(),
        ];
        if (!$editAccess['expired']) {
            $data['protection_level'] = $editAccess['level'];
            $form->get('protection_expiry')->setEmptyOption(sprintf(
                $this->translate('Existing expiration time: %s'),
                $editAccess['expiry']
                    ? $editAccess['expiry']->format('G:i, j F Y')
                    : 'infinite'
            ));
        }
        $form->setData($data);

        $sItem = $sMedia->scriptoItem();
        $view = new ViewModel;
        $view->setVariable('sMedia', $sMedia);
        $view->setVariable('media', $sMedia->media());
        $view->setVariable('sItem', $sItem);
        $view->setVariable('item', $sItem->item());
        $view->setVariable('project', $sItem->scriptoProject());
        $view->setVariable('form', $form);
        return $view;
    }

    public function batchEditAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $sItem = $this->getScriptoRepresentation(
            $this->params('project-id'),
            $this->params('item-id')
        );
        if (!$sItem) {
            return $this->redirect()->toRoute('admin/scripto-project');
        }

        $sMediaIds = $this->params()->fromPost('resource_ids', []);

        $sMedias = [];
        foreach ($sMediaIds as $sMediaId) {
            $sMedias[] = $this->api()->read('scripto_media', $sMediaId)->getContent();
        }

        $form = $this->getForm(BatchMediaForm::class);

        if ($this->params()->fromPost('batch_edit')) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $formData = $form->getData();

                // Update MediaWiki data.
                if ($this->scriptoApiClient()->userIsLoggedIn()) {
                    $titles = [];
                    foreach ($sMedias as $sMedia) {
                        $titles[] = $sMedia->pageTitle();
                    }
                    if ('1' === $formData['is_watched']) {
                        $this->scriptoApiClient()->watchPages($titles);
                    } elseif ('0' === $formData['is_watched']) {
                        $this->scriptoApiClient()->unwatchPages($titles);
                    }
                }
                if ($formData['protection_level'] && $this->scriptoApiClient()->userIsInGroup('sysop')) {
                    foreach ($sMedias as $sMedia) {
                        $this->scriptoApiClient()->protectPage(
                            $sMedia->pageTitle(),
                            $sMedia->pageIsCreated() ? 'edit' : 'create',
                            $formData['protection_level'],
                            $formData['protection_expiry']
                        );
                    }
                }

                // Update Scripto media.
                $data = [];
                if ('1' === $formData['is_completed']) {
                    $data['o-module-scripto:is_completed'] = true;
                } elseif ('0' === $formData['is_completed']) {
                    $data['o-module-scripto:is_completed'] = false;
                }
                if ('1' === $formData['is_approved']) {
                    $data['o-module-scripto:is_approved'] = true;
                } elseif ('0' === $formData['is_approved']) {
                    $data['o-module-scripto:is_approved'] = false;
                }
                if ($data) {
                    $sMediaIds = [];
                    foreach ($sMedias as $sMedia) {
                        $sMediaIds[] = $sMedia->id();
                    }
                    $this->api()->batchUpdate('scripto_media', $sMediaIds, $data);
                }

                $this->messenger()->addSuccess('Scripto media successfully edited'); // @translate
                return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('sItem', $sItem);
        $view->setVariable('sMedias', $sMedias);
        $view->setVariable('form', $form);
        return $view;
    }

    public function batchEditAllAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $sItem = $this->getScriptoRepresentation(
            $this->params('project-id'),
            $this->params('item-id')
        );
        if (!$sItem) {
            return $this->redirect()->toRoute('admin/scripto-project');
        }

        // Note that we synchronously process a batch-edit with the request
        // instead of dispatching an asynchronous job (as is typical for
        // potentially large processes). This is because MediaWiki's API:Watch
        // and API:Protect require the user to be logged in, which is impossible
        // in a job.
        $query = json_decode($this->params()->fromPost('query', []), true);
        unset(
            $query['limit'], $query['offset'],
            $query['page'], $query['per_page'],
            $query['sort_by'], $query['sort_order']
        );
        $query['scripto_item_id'] = $sItem->id();
        $response = $this->api()->search('scripto_media', $query);
        $sMedias = $response->getContent();

        $form = $this->getForm(BatchMediaForm::class);

        if ($this->params()->fromPost('batch_edit')) {
            $form->setData($this->params()->fromPost());
            if ($form->isValid()) {
                $formData = $form->getData();

                // Update MediaWiki data. Note that we don't allow users to
                // modify protection status because the MediaWiki API doesn't
                // provide batch protections. Individual requests to API:Protect
                // are relatively slow and will compound with many requests.
                if ($this->scriptoApiClient()->userIsLoggedIn()) {
                    $titles = [];
                    foreach ($sMedias as $sMedia) {
                        $titles[] = $sMedia->pageTitle();
                    }
                    if ('1' === $formData['is_watched']) {
                        $this->scriptoApiClient()->watchPages($titles);
                    } elseif ('0' === $formData['is_watched']) {
                        $this->scriptoApiClient()->unwatchPages($titles);
                    }
                }

                // Update Scripto media.
                $data = [];
                if ('1' === $formData['is_completed']) {
                    $data['o-module-scripto:is_completed'] = true;
                } elseif ('0' === $formData['is_completed']) {
                    $data['o-module-scripto:is_completed'] = false;
                }
                if ('1' === $formData['is_approved']) {
                    $data['o-module-scripto:is_approved'] = true;
                } elseif ('0' === $formData['is_approved']) {
                    $data['o-module-scripto:is_approved'] = false;
                }
                if ($data) {
                    $sMediaIds = [];
                    foreach ($sMedias as $sMedia) {
                        $sMediaIds[] = $sMedia->id();
                    }
                    $this->api()->batchUpdate('scripto_media', $sMediaIds, $data);
                }

                $this->messenger()->addSuccess('Scripto media successfully edited'); // @translate
                return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('sItem', $sItem);
        $view->setVariable('query', $query);
        $view->setVariable('count', $response->getTotalResults());
        $view->setVariable('form', $form);
        return $view;
    }
}
