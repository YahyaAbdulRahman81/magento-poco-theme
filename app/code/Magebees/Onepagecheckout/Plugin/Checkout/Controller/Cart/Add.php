<?php
namespace Magebees\Onepagecheckout\Plugin\Checkout\Controller\Cart;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Filter\LocalizedToNormalized;

class Add extends \Magento\Checkout\Controller\Cart\Add
{
    public function aroundExecute(\Magento\Checkout\Controller\Cart\Add $add, \Closure $proceed)
    {
        if ($this->_addToCartComplateToCheckout()) {
            if (!$this->_formKeyValidator->validate($this->getRequest())) {
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
            $params = $this->getRequest()->getParams();
            try {
                if (isset($params['qty'])) {
					$filter = new LocalizedToNormalized(['locale' => $this->_objectManager->get('Magento\Framework\Locale\ResolverInterface')->getLocale()]);
                    $params['qty'] = $filter->filter($params['qty']);
                }
                $product = $this->_initProduct();
                $related = $this->getRequest()->getParam('related_product');
                if (!$product) {
                    return $this->goBack();
                }
                $this->cart->addProduct($product, $params);
                if (!empty($related)) {
                    $this->cart->addProductsByIds(explode(',', $related));
                }
                $this->cart->save();
                $this->_eventManager->dispatch(
                    'checkout_cart_add_product_complete',
                    ['product' => $product, 'request' => $this->getRequest(), 'response' => $this->getResponse()]
                );
                if (!$this->_checkoutSession->getNoCartRedirect(true)) {
                    if ($this->_addToCartComplateToCheckout()) {
                        if ($this->getRequest()->isAjax()) {

                            return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData([
                                'backUrl' => $this->_url->getUrl('checkout', array('_secure' => true)),
                            ]);
                        }
                        return $this->resultRedirectFactory->create()->setPath('checkout');
                    }
                    return $this->goBack(null, $product);
                }
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                if ($this->_checkoutSession->getUseNotice(true)) {
                    $this->messageManager->addNotice(
                        $this->_objectManager->get('Magento\Framework\Escaper')->escapeHtml($e->getMessage())
                    );
                } else {
                    $messages = array_unique(explode("\n", $e->getMessage()));
                    foreach ($messages as $message) {
                        $this->messageManager->addError(
                            $this->_objectManager->get('Magento\Framework\Escaper')->escapeHtml($message)
                        );
                    }
                }

                $url = $this->_checkoutSession->getRedirectUrl(true);

                if (!$url) {
                    $cartUrl = $this->_objectManager->get('Magento\Checkout\Helper\Cart')->getCartUrl();
                    $url = $this->_redirect->getRedirectUrl($cartUrl);
                }

                return $this->goBack($url);

            } catch (\Exception $e) {
                $this->messageManager->addException($e, __('We can\'t add this item to your shopping cart right now.'));
                $this->_objectManager->get('Psr\Log\LoggerInterface')->critical($e);
                return $this->goBack();
            }
        } else {
            $result = $proceed();
            return $result;
        }
    }
    protected function _addToCartComplateToCheckout()
    {
		return $this->_objectManager->get('Magebees\Onepagecheckout\Helper\Configurations')->getEnable() &&
        $this->_objectManager->get('Magebees\Onepagecheckout\Helper\Configurations')->redirectCheckoutAfterAddProduct();
    }
}