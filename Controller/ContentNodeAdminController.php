<?php

namespace MandarinMedien\MMCmf\Admin\ContentNodeAddonBundle\Controller;

use MandarinMedien\MMAdminBundle\Controller\BaseController;
use MandarinMedien\MMAdminBundle\Frontend\Widget\Admin\AdminListWidget;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use MandarinMedien\MMCmfAdminBundle\Response\JsonFormResponse;
use MandarinMedien\MMCmfAdminBundle\Form\ContentNodeType;
use MandarinMedien\MMCmfContentBundle\Entity\ContentNode;
use MandarinMedien\MMCmfNodeBundle\Entity\Node;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ContentNodeAdminController extends BaseController
{


    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $entities = $em->getRepository(ContentNode::class)->findAll();

        return $this->render("MMCmfAdminContentNodeAddonBundle:ContentNode:list.html.twig", array(
            'contentnodes' => $entities,
            'contentParser' => $this->get('mm_cmf_content.content_parser'),
            'contentNodeFactory' => $this->get('mm_cmf_content.content_node_factory')
        ));
    }


    public function newAction(Request $request, $contentnode_type)
    {
        $factory    = $this->get('mm_cmf_content.content_node_factory');
        $repository = $this->getDoctrine()->getRepository(Node::class);
        $contentParser = $this->container->get('mm_cmf_content.content_parser');

        $parent_node = null;
        $entity = $factory->createContentNode($contentnode_type);

        if((int) $request->get('parent_node')) {
            if($parent_node = $repository->find((int)$request->get('parent_node'))) {
                $entity->setParent($parent_node);
            }
        }

        $form   = $this->createCreateForm($entity);

        return $this->render(
            '@MMCmfAdminContentNodeAddon/ContentNode/new.html.twig',
            array(
                'entity' => $entity,
                'form' => $form->createView()
            )
        );
    }


    public function createAction(Request $request, $contentnode_type)
    {

        $factory = $this->get('mm_cmf_content.content_node_factory');

        $entity = $factory->createContentNode($contentnode_type);

        $form = $this->createCreateForm($entity);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($entity);
            $em->flush();
        }

        return $this->formResponse($form);
    }



    private function createCreateForm(ContentNode $entity)
    {

        $contentNodeFactory = $this->get('mm_cmf_content.content_node_factory');

        $form = $this->createForm(
            \MandarinMedien\MMCmfContentBundle\Form\ContentNodeType::class,
            $entity,
            array(
                'root_node' => $entity->getParent(),
                'action' => $this->generateUrl('mm_cmf_admin_content_node_addon_contentnode_create',
                    array('contentnode_type' => $this->get('mm_cmf_content.content_node_factory')->getDiscriminatorByClass($entity)
                    )),
                'method' => 'POST',
            )
        );

        $form
            ->add('submit', SubmitType::class, array('label' => 'save'))
            ->add('save_and_add', SubmitType::class, array(
                'attr' => array(
                    'data-target' => $this->container->get('router')->generate('mm_cmf_admin_content_node_addon_contentnode_new', array(
                        'discriminator' => $contentNodeFactory->getDiscriminatorByClass($entity)
                    ))
                ),
            ))
            ->add('save_and_back', SubmitType::class, array(
                'attr' => array(
                    'data-target' => $this->container->get('router')->generate('mm_cmf_admin_content_node_addon_contentnode')
                )
            ));

        return $form;
    }


    /**
     * Displays a form to edit an existing ContentNode entity.
     */
    public function editAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository(ContentNode::class)->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find ContentNode entity.');
        }

        $form = $this->createEditForm($entity);

        return $this->render('@MMCmfAdminContentNodeAddon/ContentNode/edit.html.twig', array(
            'entity'      => $entity,
            'form'   => $form->createView(),
        ));
    }



    private function createEditForm(ContentNode $entity)
    {

        $contentNodeFactory = $this->get('mm_cmf_content.content_node_factory');

        $form = $this->createForm(
            \MandarinMedien\MMCmfContentBundle\Form\ContentNodeType::class,
            $entity, array(
            'action' => $this->generateUrl('mm_cmf_admin_content_node_addon_contentnode_update', array('id' => $entity->getId())),
            'method' => 'PUT',
            'attr' => array(
                'rel' => 'ajax'
            )
        ));

        $form
            ->add('submit', SubmitType::class, array('label' => 'save'))
            ->add('save_and_add', SubmitType::class, array(
                'attr' => array(
                    'data-target' => $this->container->get('router')->generate('mm_cmf_admin_content_node_addon_contentnode_new', array(
                        'discriminator' => $contentNodeFactory->getDiscriminatorByClass($entity)
                    ))
                ),
            ))
            ->add('save_and_back', SubmitType::class, array(
                'attr' => array(
                    'data-target' => $this->container->get('router')->generate('mm_cmf_admin_content_node_addon_contentnode')
                )
            ));

        return $form;
    }


    public function updateAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository(ContentNode::class)->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find ContentNode entity.');
        }

        $form = $this->createEditForm($entity);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $em->flush();
        }

        return $this->formResponse($form);

    }

    public function deleteAction(Request $request, $id)
    {


        $form = $this->createDeleteForm($id);
        $form->handleRequest($request);

        //if ($form->isValid()) {
        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository(ContentNode::class)->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find ContenNode entity.');
        }

        if($parent = $entity->getParent())
        {
            $parent->removeNode($entity);
        }

        $em->remove($entity);
        $em->flush();
        //}

        return $this->redirect($this->generateUrl('mm_cmf_admin_content_node_addon_contentnode'));
    }


    private function createDeleteForm($id)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('mm_cmf_admin_content_node_addon_contentnode_delete', array('id' => $id)))
            ->setMethod('DELETE')
            ->add('submit', SubmitType::class, array('label' => 'Delete'))
            ->getForm()
            ;
    }

    public function toggleVisibilityAction(Node $entity, Request $request)
    {
        $manager = $this->getDoctrine()->getManager();
        $entity->setVisible($entity->isVisible() ? false : true);
        $manager->flush();

        $referer = $request->headers->get('referer');
        return $this->redirect($referer);
    }
}
