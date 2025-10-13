<?php
namespace Magebees\Responsivebannerslider\Block\Adminhtml\Slidergroup\Edit\Tab;
class Categories extends \Magento\Backend\Block\Widget\Form\Generic //implements \Magento\Backend\Block\Widget\Tab\TabInterface
{
    /**
     * @var \Magento\Store\Model\System\Store
     */
    protected $_systemStore;
	protected $_responsivebannerslider;
	protected $_cmsPage;
	protected $_categorytree;
    protected $categoryFlatConfig;
    protected $categories;
   
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Store\Model\System\Store $systemStore,
		\Magebees\Responsivebannerslider\Model\Responsivebannerslider $responsivebannerslider,
		\Magebees\Responsivebannerslider\Model\Categories $categories,
		\Magento\Catalog\Model\ResourceModel\Category\Tree $categorytree,
        \Magento\Catalog\Model\Category $categoryFlatState,
       	
        array $data = array()
    ) {
        $this->_systemStore = $systemStore;
		$this->_categorytree = $categorytree;
        $this->categoryFlatConfig = $categoryFlatState;
     	$this->_responsivebannerslider = $responsivebannerslider;
     	$this->_categories = $categories;
				
        parent::__construct($context, $registry, $formFactory, $data);
    }

	public function buildCategoriesMultiselectValues($node, $values, $level = 0)
    {
        $nonEscapableNbspChar = html_entity_decode('&#160;', ENT_NOQUOTES, 'UTF-8');

        $level++;
        if ($level > 1) {
            $values[$node->getId()]['value'] = $node->getId();
            $values[$node->getId()]['label'] = str_repeat($nonEscapableNbspChar, ($level - 2) * 5).$node->getName();
        }

        foreach ($node->getChildren() as $child) {
            $values = $this->buildCategoriesMultiselectValues($child, $values, $level);
        }

        return $values;
    }

    public function toOptionArray()
    {
        $tree = $this->_categorytree->load();

        $parentId = 1;

        $root = $tree->getNodeById($parentId);

        if($root && $root->getId() == 1) {
            $root->setName('Root');
        }

        $collection = $this->categoryFlatConfig->getCollection()
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('is_active');

		$tree->addCollectionData($collection, true);

        $values['---'] = array(
            'value' => '',
            'label' => '',
        );
        return $this->buildCategoriesMultiselectValues($root, $values);
    }
	
    protected function _prepareForm()
    {

        $model = $this->_coreRegistry->registry('slidergroup_data');
		$isElementDisabled = false;
        $form = $this->_formFactory->create();

        $form->setHtmlIdPrefix('categories_');

        $fieldset = $form->addFieldset('base_fieldset', array('legend' => __('Categories')));

		$categories = $this->_categories->getCollection()
			->addFieldToFilter('slidergroup_id',array('eq' => $model->getId()));
		$categories_val = array();
		foreach($categories as $categories_data){
			$categories_val[] = $categories_data->getData('category_ids');
		}	
		       

		$group_name = $fieldset->addField(
            'categories',
            'multiselect',
            [
                'name' => 'categories[]',
                'label' => __('Visible In'),
                'title' => __('Visible In'),
                'required' => false,
                'values' => $this->toOptionArray(),
				'disabled' => $isElementDisabled,
				'value'		=> $categories_val,
            ]
        );
		
	    
        $this->setForm($form);
		 
        return parent::_prepareForm();   
    }

   
  

    protected function _isAllowedAction($resourceId)
    {
        return $this->_authorization->isAllowed($resourceId);
    }
}
