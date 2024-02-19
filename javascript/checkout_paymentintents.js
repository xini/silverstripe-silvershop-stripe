/**
 * Hooks into the checkout form, activating the Stripe.js api to retrieve a token and store it in a hidden field.
 * It doesn't depend on jQuery or any other javascript library.
 */
(function(window, document, undefined) {
	'use strict';

	document.addEventListener('DOMContentLoaded', function() {

		var config = window.StripeConfig,
			form = document.getElementById(config.formID);

		if (!config) {
			console.error('StripeConfig was not set');
			return;
		}
		if (!form) {
			console.error('Form was not found on the page!', config.formID);
			return;
		}

		var submitButton = document.getElementById(config.submitButton);

		var stripe = Stripe(config.key, {
      apiVersion: '2020-08-27'
    });


		var elements = stripe.elements();

		var style = {
			base: {
				color: '#111',
				lineHeight: '24px',
				fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
				fontSmoothing: 'antialiased',
				fontSize: '18px',
				'::placeholder': {
					color: '#888'
				}
			},
			invalid: {
				color: '#961c13',
				iconColor: '#961c13'
			}
		};


		var card = elements.create('card', {style: style});

		// check if card selection field is present
		var selectionFields = document.querySelectorAll('input[name="SavedCreditCardID"]') || document.querySelectorAll('select[name="SavedCreditCardID"]');
		if (selectionFields && selectionFields.length) {
			function findAncestor (el, cls) {
			    while ((el = el.parentElement) && !el.classList.contains(cls));
			    return el;
			}
			function updateCardField() {
				var current = document.querySelector('input[name="SavedCreditCardID"]:checked') || document.querySelector('select[name="SavedCreditCardID"]');
				if (current && current.value == 'newcard') {
					findAncestor(document.getElementById(config.stripeField), 'field').style.display = 'block';
					card.mount('#' + config.stripeField);
				} else {
					card.unmount('#' + config.stripeField);
					findAncestor(document.getElementById(config.stripeField), 'field').style.display = 'none';
				}
			};
			// attache change event
			Array.prototype.forEach.call(selectionFields, function (selectionField) {
				selectionField.addEventListener('change', updateCardField);
			});
			// run update
			updateCardField();
		} else {
			// mount card field without selector
			card.mount('#' + config.stripeField);
		}

    var form = submitButton.form;
    var errorElement = document.getElementById('card-errors');

    submitButton.addEventListener('click', (event) => {
      event.preventDefault();
      // errorElement.setAttribute('style', 'display: none;');

      stripe.createPaymentMethod({
        type: 'card',
        card: card
      }).then((result) => {

        if (result.error) {
          // Inform the user if there was an error
          errorElement.textContent = result.error.message;
          errorElement.setAttribute('style', 'display: block;');
        } else {
          // Submit form
          stripeTokenHandler(result.paymentMethod)
          // form.querySelector('input[name="StripeToken"]').value = result.paymentMethod.id;
          // form.submit();
        }
      });
    });



		function stripeTokenHandler(token) {
			// Insert the token ID into the form so it gets submitted to the server
			var hiddenInput = document.getElementById(config.tokenField);
			hiddenInput.setAttribute('value', token.id);

			// Submit the form
			form.submit();
		}

		// function createToken() {
		// 	stripe.createToken(card).then(function(result) {
		// 	    if (result.error) {
		// 	    	// Inform the user if there was an error
		// 	    	var errorElement = document.getElementById(config.formID + '_error');
		// 	    	errorElement.textContent = result.error.message;
		// 	    	errorElement.classList.add('error');
		// 	    	errorElement.style.display = 'block';
		// 	    } else {
		// 	    	// Send the token to your server
		// 	    	stripeTokenHandler(result.token);
		// 		}
		// 	});
		// };
    //
		// form.addEventListener('submit', function(e) {
		// 	var selectedSavedCard = document.querySelector('input[name="SavedCreditCardID"]:checked') || document.querySelector('select[name="SavedCreditCardID"]');
		// 	if (!selectedSavedCard || selectedSavedCard.value == 'newcard') {
		// 		e.preventDefault();
		// 		createToken();
		// 	}
		// });

	});

})(this, this.document);
