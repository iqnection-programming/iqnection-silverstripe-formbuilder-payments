(function($){
	"use strict";
	window._formBuilderActions = window._formBuilderActions || {};
	$.extend(window._formBuilderActions, {
		actionAdjustPaymentAmount: function(actionData, result) {
			// find the target field
			var target = $(actionData.selector)[0];
			if (target !== undefined) {
				target._formBuilderAdjustments = target._formBuilderAdjustments || {adjustments: {}};
				if (target._formBuilderAdjustments.override === undefined) {
					// add this adjustment if the conditions are true
					target._formBuilderAdjustments.adjustments[actionData.id] = result ? actionData.amount.adjust : 0;
					target.amount = parseFloat(actionData.amount._default);
					var values = Object.values(target._formBuilderAdjustments.adjustments);
					// calculate the total of all conditions
					for(var k=0; k < values.length; k++) {
						target.amount += parseFloat(values[k]);
					}
					// assign the total to the input
					setTimeout(function(){
						$(target).val('$'+target.amount.toString()).trigger('change');
					}, 0);
				}
			}
		},
		actionSetPaymentAmount: function(actionData, result) {
			// find the target field
			var target = $(actionData.selector)[0];
			if (target !== undefined) {
				target._formBuilderAdjustments = target._formBuilderAdjustments || {adjustments: {}};
				target.amount = 0;
				if (result) {
					// assign the total to the input
					target.amount = parseFloat(actionData.amount.adjust);
					target._formBuilderAdjustments.override = target.amount;
				} else {
					target.amount = parseFloat(actionData.amount._default);
					target._formBuilderAdjustments.override = undefined;
					var values = Object.values(target._formBuilderAdjustments.adjustments);
					// calculate the total of all conditions
					for(var k=0; k < values.length; k++) {
						target.amount += parseFloat(values[k]);
					}
				}
				setTimeout(function(){
					$(target).val('$'+target.amount.toString()).trigger('change');
				}, 0);
			}
		}
	});
}(jQuery));