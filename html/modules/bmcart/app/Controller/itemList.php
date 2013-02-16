<?php
/**
 * Created by JetBrains PhpStorm.
 * Copyright(c): Bluemoon inc.
 * Author : Yoshi Sakai
 * Date: 2012/12/28
 * Time: 14:13
 * To change this template use File | Settings | File Templates.
 */
require_once _MY_MODULE_PATH . 'app/Model/Item.php';
require_once _MY_MODULE_PATH . 'app/Model/PageNavi.class.php';
require_once _MY_MODULE_PATH . 'app/View/view.php';

class Controller_ItemList extends AbstractAction {
	protected $mPrimaryKey = 'item_id';
	protected $imageObjects;
	protected $image_id;
	protected $category_id = 0;
	protected $sortName = "item_name";
	protected $sortOrder = "asc";
	protected $breadcrumbs;
	protected $categoryHandler;
	protected $comment_flag = false;
	/**
	 * constructor
	 */
	public function __construct(){
		parent::__construct();
		$this->mHandler = Model_Item::forge();
		$this->categoryHandler = xoops_getModuleHandler('category');
	}
	public function action_index(){
		if (xoops_getrequest('item_id')){
			$this->_get_itemDetail();
		}else{
			$this->indexDefault($this->mPrimaryKey);
			$this->category_id = $_SESSION['bmcart']['category_id'] ? $_SESSION['bmcart']['category_id'] : NULL;
			$categoryArray = $this->mHandler->getCategoryArray($this->category_id);
			if($categoryArray){
				$categories = new Criteria('category_id', implode(",",$categoryArray), "IN");
				$this->mPagenavi->addCriteria($categories);
			}
			$this->mPagenavi->addSort($this->sortName,$this->sortOrder);
			$this->mListData = $this->mHandler->getItemList( $this->mPagenavi->getCriteria() );
			$this->breadcrumbs = $this->categoryHandler->makeBreadcrumbs($this->category_id);
			$this->template = 'itemList.html';
		}
	}
	public function action_category(){
		$this->indexDefault($this->mPrimaryKey);
		if (isset($this->mParams[0])){
			$_SESSION['bmcart']['category_id'] = intval($this->mParams[0]);
		}
		$this->breadcrumbs = $this->categoryHandler->makeBreadcrumbs($this->category_id);
		$this->action_index();
	}
	public function action_sortBy(){
		$_SESSION['bmcart']['category_id']=$this->mParams[0];
		if (in_array($this->mParams[1],array('item_name','price','stock_qty','last_update'))){
			$this->sortName = $this->mParams[1];
		}
		if (in_array($this->mParams[2],array('asc','desc'))){
			$this->sortOrder = $this->mParams[2];
		}
		$this->action_index();
	}
	private function _get_itemDetail(){
		if (xoops_getrequest('item_id')){
			$item_id = intval(xoops_getrequest('item_id'));
		}elseif (isset($this->mParams[0])){
			$item_id = intval($this->mParams[0]);
		}
		if (isset($this->mParams[1])) $this->image_id = intval($this->mParams[1]);
		$this->mListData = $this->mHandler->getItemDetail($item_id);
		$_SESSION['bmcart']['category_id'] = $this->category_id = $this->mListData['category_id'];
		$imageHandler = xoops_getmodulehandler('itemImages');
		$this->imageObjects = $imageHandler->getImages($item_id);
		if (!$this->image_id && $this->imageObjects){
			$this->image_id = $this->imageObjects[0]->getVar('image_id');
		}
		$this->breadcrumbs = $this->categoryHandler->makeBreadcrumbs($this->category_id);
		$this->template = 'itemDetail.html';
		$_GET['item_id'] = $item_id;    // for comment_view
		$this->comment_flag = true;
	}
	public function action_itemDetail(){
		if(preg_match("/^comment_(.*)/",$this->mParams[0])){
			// using core comment code
			$com_itemid = $this->root->mContext->mRequest->getRequest('com_itemid');
			$xoopsUser = $this->root->mContext->mXoopsUser;
			$xoopsModule = $this->root->mContext->mXoopsModule;
			$xoopsModuleConfig = $this->root->mContext->mModuleConfig;
			global $xoopsTpl;
			$_POST['page'] = 'itemDetail' ;
			$_POST['com_id'] = $this->root->mContext->mRequest->getRequest('com_id');
			if (isset($_GET['com_order'])){
				$_POST['com_order'] = $this->root->mContext->mRequest->getRequest('com_order');
			}else{
				$_GET['com_order'] = 1;
			}
			$action = preg_replace("/_php$/i",".php",$this->mParams[0]);
			require XOOPS_ROOT_PATH . '/include/'.$action;
		}else{
			$this->_get_itemDetail();
		}
	}
	public function action_addtocart(){
		if (isset($this->mParams[0])) $item_id = intval($this->mParams[0]);
		$this->mListData = $this->mHandler->getItemDetail($item_id);
		$cartHandler = xoops_getModuleHandler('cart');
		$cartHandler->addToCart($item_id);
		$this->executeRedirect("../../cartList", 0, 'Add to Cart');
	}
	private function _comment_view(){
		// include comment from core
		$xoopsConfig = $this->root->mContext->mXoopsConfig;
		$xoopsUser = $this->root->mContext->mXoopsUser;
		$xoopsModule = $this->root->mContext->mXoopsModule;
		$xoopsModuleConfig = $this->root->mContext->mModuleConfig;
		global $xoopsTpl;
		require_once XOOPS_ROOT_PATH.'/include/comment_view.php';
	}

	public function action_view(){
		$free_shipping = intval($this->root->mContext->mModuleConfig['free_shipping']);
		if ($free_shipping==0) $free_shipping = 99999999;
		$view = new View($this->root);
		$view->setTemplate($this->template);
		$view->set('ListData', $this->mListData);
		$view->set('imageObjects', $this->imageObjects);
		$view->set('breadcrumbs', $this->breadcrumbs);
		$view->set('current_category', $this->category_id);
		$view->set('current_image', $this->image_id);
		$view->set('ticket_hidden',$this->mTicketHidden);
		$view->set('free_shipping', $free_shipping);
		if (is_object($this->mPagenavi)) {
			$view->set('pageNavi', $this->mPagenavi->getNavi());
		}
		if ($this->comment_flag){
			$this->_comment_view();
		}
	}
//-----------------
// protected
//-----------------
	protected function setPageNavi($sortName, $sortIndex)
	{
		$this->setPageNaviDefault($sortName, $sortIndex);
	}
}