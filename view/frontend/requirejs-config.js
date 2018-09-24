/*jshint browser:true jquery:true*/
/*global alert*/
var config = {
    config: {
        //mixins: {
        //    'PayEx_Payments/js/action/place-order': {
        //        'Magento_CheckoutAgreements/js/model/place-order-mixin': true
        //    }
        //}
        mixins: {
            'Magento_Checkout/js/model/error-processor': {
                'PayEx_Payments/js/model/error-processor-mixin': true
            }
        }
    }
    // Saving payment method at backend leaded to problems with messed payment methods (Payever & Payex)
    // map: {
    //     '*': {
    //         'Magento_Checkout/js/action/select-payment-method':
    //             'PayEx_Payments/js/action/select-payment-method'
    //     }
    // }
};
