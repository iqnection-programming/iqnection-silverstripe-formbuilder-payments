(function($){
	"use strict";
	window._formBuilderActions = window._formBuilderActions || {};
	$.extend(window._formBuilderActions, {
		actionAdjustPaymentAmount: function(actionData, result) {
			// find the target field
			var target = $(actionData.selector)[0];
			if (target !== undefined) {
				target._formBuilderAdjustments = target._formBuilderAdjustments || {};
				// add this adjustment if the conditions are true
				target._formBuilderAdjustments[actionData.id] = result ? actionData.amount.adjust : 0;
				var amount = parseFloat(actionData.amount.default);
				var values = Object.values(target._formBuilderAdjustments);
				// calculate the total of all conditions
				for(var k=0; k < values.length; k++) {
					amount += parseFloat(values[k]);
				}
				// assign the total to the input
				$(target).val('$'+amount.toString());
			}
		}
	});
}(jQuery));