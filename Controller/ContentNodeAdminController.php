<?php

namespace MandarinMedien\MMCmf\Admin\ContentNodeAddonBundle\Controller;

use MandarinMedien\MMAdminBundle\Controller\AdminController;
use MandarinMedien\MMAdminBundle\Controller\BaseController;
use MandarinMedien\MMAdminBundle\Frontend\Widget\Admin\AdminListWidget;
use MandarinMedien\MMAdminBundle\Frontend\Widget\BreadcrumbWidget;
use MandarinMedien\MMCmfContentBundle\Form\Type\TemplatableNodeTemplateType;
use MandarinMedien\MMCmfNodeBundle\Entity\NodeInterface;
use MandarinMedien\MMCmfRoutingBundle\Entity\RoutableNodeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use MandarinMedien\MMCmfAdminBundle\Response\JsonFormResponse;

use MandarinMedien\MMCmfContentBundle\Entity\ContentNode;
use MandarinMedien\MMCmfNodeBundle\Entity\Node;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use MandarinMedien\MMAdminBundle\Form\Group\BoxType;
use MandarinMedien\MMAdminBundle\Form\Group\TabbedBoxType;
use MandarinMedien\MMAdminBundle\Form\Group\TabType;
use MandarinMedien\MMCmfContentBundle\Form\FormTypeMetaReader;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class ContentNodeAdminController
 * @package MandarinMedien\MMCmf\Admin\ContentNodeAddonBundle\Controller
 */
class ContentNodeAdminController extends AdminController
{

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $entities = $em->getRepository(ContentNode::class)->findAll();

        return $this->render(
            '@MMCmfAdminContentNodeAddon/ContentNode/list.html.twig',
            array(
                'contentNodes' => $entities,
                'contentParser' => $this->get('mm_cmf_content.content_parser'),
                'contentNodeFactory' => $this->get('mm_cmf_node.factory')
            ));
    }

    /**
     * @param Request $request
     * @param $contentnode_type
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function newAction(Request $request, $contentnode_type)
    {
        $factory = $this->get('mm_cmf_content.content_node_factory');
        $repository = $this->getDoctrine()->getRepository(Node::class);
        $contentParser = $this->container->get('mm_cmf_content.content_parser');

        $parent_node = null;
        $entity = $factory->createContentNode($contentnode_type);

        if ((int)$request->get('parent_node')) {
            if ($parent_node = $repository->find((int)$request->get('parent_node'))) {
                $entity->setParent($parent_node);
            }
        }

        $form = $this->createCreateForm($entity);

        return $this->render(
            '@MMCmfAdminContentNodeAddon/ContentNode/new.html.twig',
            array(
                'entity' => $entity,
                'form' => $form->createView()
            )
        );
    }

    /**
     * @param Request $request
     * @param $contentnode_type
     * @return \MandarinMedien\MMAdminBundle\Controller\JsonFormResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Exception
     */
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

    /**
     * @param ContentNode $entity
     * @return \Symfony\Component\Form\Form
     */
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
//    public function editAction(Request $request, $id)
//    {
//        $em = $this->getDoctrine()->getManager();
//
//        $entity = $em->getRepository(ContentNode::class)->find($id);
//
//        if (!$entity) {
//            throw $this->createNotFoundException('Unable to find ContentNode entity.');
//        }
//
//        $form = $this->createEditForm($entity);
//
//        return $this->render('@MMCmfAdminContentNodeAddon/ContentNode/edit.html.twig', array(
//            'entity' => $entity,
//            'form' => $form->createView(),
//        ));
//    }


    /**
     * Node Edit Action
     * @param Request $request
     * @param Node $node
     * @return Response
     */
    public function editAction(Request $request, Node $node)
    {
        $this->getContent()->setPageTitle(get_class($node) . ' bearbeiten');

        $admin = $this->getAdmin($node);
        $form = $admin->getGroup()->getForm();
        //$contentNodeFactory = $this->get('mm_cmf_content.content_node_factory');
        $nodeFactory = $this->get('mm_cmf_node.factory');

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $manager = $this->getDoctrine()->getManager();
            $manager->flush();

            if ($form->get('saveAndNew')->isClicked()) return $this->goToNew($node);
            if ($form->get('saveAndBack')->isClicked()) return $this->goToParent($node);

        }

        $content = $this->getContent();
        $content
            ->addChild($admin);

        if ($nodeFactory->getChildNodeDefinition(get_class($node))) {
            $content->addChild($this->getNodeList($node, CinemaRelatedNode::class, $request));
        }

        if ($node instanceof RoutableNodeInterface) {
            $content->addChild($this->getRouteList($node));
        }

        return $this->render();

    }


    /**
     * @param ContentNode $entity
     * @return \Symfony\Component\Form\Form
     */
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

    /**
     * @param Request $request
     * @param $id
     * @return \MandarinMedien\MMAdminBundle\Controller\JsonFormResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
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

    /**
     * @param Request $request
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
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

        if ($parent = $entity->getParent()) {
            $parent->removeNode($entity);
        }

        $em->remove($entity);
        $em->flush();
        //}

        return $this->redirect($this->generateUrl('mm_cmf_admin_content_node_addon_contentnode'));
    }

    /**
     * @param $id
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    private function createDeleteForm($id)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('mm_cmf_admin_content_node_addon_contentnode_delete', array('id' => $id)))
            ->setMethod('DELETE')
            ->add('submit', SubmitType::class, array('label' => 'Delete'))
            ->getForm();
    }

    /**
     * @param Node $entity
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function toggleVisibilityAction(Node $entity, Request $request)
    {
        $manager = $this->getDoctrine()->getManager();
        $entity->setVisible($entity->isVisible() ? false : true);
        $manager->flush();

        $referer = $request->headers->get('referer');
        return $this->redirect($referer);
    }


    /**
     * @param Node $node
     * @return \MandarinMedien\MMAdminBundle\Frontend\Widget\Admin\AdminFormGroupWidget
     * @throws \Exception
     */
    protected function getAdmin(Node $node)
    {
        $className = get_class($node);


        $admin = $this->createAdmin(FormType::class, $node, ['data_class' => $className]);
        $builder = $admin->getGroupBuilder();

        $tabs = $builder->add('Tabber', TabbedBoxType::class, ['attr' => ['class' => 'col-xs-12 col-md-8']]);
        $general = $tabs->add('General', TabType::class, ['active' => true]);
        $mainTab = $tabs->add('Contents', TabType::class);
        $propertiesTab = $tabs->add('Properties', TabType::class);


        $metaData = $this->getDoctrine()->getManager()->getClassMetadata($className);
        $formTypeReader = new FormTypeMetaReader();

        $usedFields = ['id', 'name', 'position', 'template', 'visible', 'classes', 'attributes'];

        /**
         * build form fields for scalar types
         */
        foreach ($metaData->getFieldNames() as $field) {
            if (in_array($field, $usedFields)) continue;

            $formTypeMeta = $formTypeReader->getFormTypeMeta($className, $field);
            if ($formTypeMeta) {
                $mainTab->add($field, $formTypeMeta->getClass(), $formTypeMeta->getOptions());
            } else {
                $mainTab->add($field);
            }
        }

        /**
         * build form field for assocaiation types
         */
        foreach ($metaData->getAssociationNames() as $field) {

            if (in_array($field, array(
                'parent',
                'routes',
                'template',
                'cinemas'
            ))) continue;


            $formTypeMeta = $formTypeReader->getFormTypeMeta($className, $field);
            if ($formTypeMeta) {
                $mainTab->add($field, $formTypeMeta->getClass(), $formTypeMeta->getOptions());
            } else {

                if ($field !== 'nodes') {
                    $mainTab->add($field);
                }
            }
        }

        /**
         * general fields
         */
        $general
            ->add('visible', ChoiceType::class, ['choices' => [
                'ja' => true,
                'nein' => false
            ],
                'multiple' => false,
                'expanded' => true,
                'renderButtons' => true
            ])->end();

        if ($node instanceof RoutableNodeInterface)
            $general
                ->add('autoNodeRouteGeneration', ChoiceType::class, ['choices' => [
                    'ja' => true,
                    'nein' => false
                ],
                    'multiple' => false,
                    'expanded' => true,
                    'renderButtons' => true
                ]);

        $general
            ->add('name')->end()
            ->add('parent')->end()
            ->add('position')->end();

        $propertiesTab
            #->add('classes')->end()
            #->add('attributes')->end()
            ->add('template', TemplatableNodeTemplateType::class, [
                'className' => $className
            ])->end();


        $builder->add('actions', BoxType::class, ['attr' => ['class' => 'col-xs-12 col-md-4'], 'title' => 'Aktionen'])
            ->add('save', SubmitType::class)->end()
            ->add('saveAndBack', SubmitType::class)->end()
            ->add('saveAndNew', SubmitType::class)->end();


        return $admin;
    }


    /**
     * create page specific node list
     * @param NodeInterface $node
     * @param string $class classname of NodeInterface
     * @return AdminListWidget
     */
    protected function getNodeList(NodeInterface $node = null, string $class = Node::class, Request $request = null)
    {

        $nodeFactory = $this->get('mm_cmf_node.factory');

        $translator = $this->get('translator');

        $paginationParam = 'page_nodes';
        $sortParam = 'sort_nodes';

        $router = $this->get('router');

        $list = new AdminListWidget($this->container, $class);

        $pagination = ((int)$request->get($paginationParam, 1)) ?: 1;

        $list
            ->setTemplate("AdminBundle:Admin:nodelist.html.twig")
            ->setPaginationParam($paginationParam)
            ->setSortParam($sortParam)
            ->setTitle('Inhalte')
            ->setPage($pagination)
            ->add('position')
            ->add('id')
            ->add('name', ['block_prefix' => 'raw', 'value' => function (Node $node, string $field) use ($router) {
                $path = $router->generate('mm_cmf_admin_content_node_addon_contentnode_edit', ['id' => $node->getId()]);
                return '<a href="' . $path . '">' . $node->getName() . '</a>';
            }])
            ->add('type', [
                'value' => function (Node $entity, $property) use ($nodeFactory, $translator) {
                    return $translator->trans($nodeFactory->getDiscriminatorByClass($entity));
                },
                'mapped' => false
            ])
            ->addRowAction('preview', [
                'icon' => 'fa-external-link',
                'label' => 'Vorschau',
                'url' => function (RoutableNodeInterface $node, RouterInterface $router) {

                    if ($node && $node->getRoutes()->count()) {
                        return $router->generate('mm_cmf_node_route', [
                            'route' => $node->getRoutes()->first()->getRoute()
                        ]);
                    }
                },
                'attr' => function (RoutableNodeInterface $node) {


                    // get the next routable
                    while ($node && ($node instanceof RoutableNodeInterface) === false) $node = $node->getParent();

                    if ($node && $node->getRoutes()->count()) {
                        return [
                            'target' => '_blank',
                            'class' => 'btn btn-default'
                        ];
                    }


                    return [
                        'disabled' => 'disabled',
                        'class' => 'btn btn-default'
                    ];
                }
            ])
            ->addRowAction('check', [
                'icon' => function (Node $node) {
                    if ($node->isVisible()) {
                        return 'fa-eye-slash';
                    } else {
                        return 'fa-eye';
                    }
                },
                'url' => function (Node $node, RouterInterface $router) {
                    return $router->generate('mm_cmf_admin_content_node_addon_node_visibilty_toggle', ['id' => $node->getId()]);
                }
            ])
            ->addEditAction('cms_node_edit')
            ->addDeleteAction('cms_node_delete')
            ->addAction('create', [
                'node' => $node,
                'types' => $nodeFactory->getChildNodeDefinition(get_class($node)) ? array_map(
                    function ($subclass) use ($nodeFactory) {
                        return $nodeFactory->getDiscriminatorByClassName($subclass);
                    }, $nodeFactory->getChildNodeDefinition(get_class($node))) : [$nodeFactory->getDiscriminatorByClassName(Node::class)]
            ]);

        $qb = $list->getQueryBuilder();

        if ($node) {
            $qb->andWhere('e.parent = :parent')
                ->orderBy('e.position', 'ASC')
                ->setParameter('parent', $node ? $node->getId() : null);
        } else {
            $qb->andWhere('e.parent IS NULL')
                ->orderBy('e.position', 'ASC');
        }

        return $list;
    }

}
