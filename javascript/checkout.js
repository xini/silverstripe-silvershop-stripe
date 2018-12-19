/**
 * Hooks into the checkout form, activating the Stripe.js api's to retrieve a token and store it in a hidden field.
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

		var stripe = Stripe(config.key);
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
		card.mount('#' + config.stripeField);
		
		function stripeTokenHandler(token) {
			// Insert the token ID into the form so it gets submitted to the server
			var hiddenInput = document.getElementById(config.tokenField);
			hiddenInput.setAttribute('value', token.id);

			// Submit the form
			form.submit();
		}
		
		function createToken() {
			stripe.createToken(card).then(function(result) {
			    if (result.error) {
			    	// Inform the user if there was an error
			    	var errorElement = document.getElementById(config.formID + '_error');
			    	errorElement.textContent = result.error.message;
			    	errorElement.classList.add('error');
			    	errorElement.style.display = 'block';
			    } else {
			    	// Send the token to your server
			    	stripeTokenHandler(result.token);
				}
			});
		};		

		form.addEventListener('submit', function(e) {
			e.preventDefault();
			createToken();
		});

	});
		
})(this, this.document);
