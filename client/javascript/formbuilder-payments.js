window._formBuilderActions = window._formBuilderActions || {};
(function($){
	"use strict";
	$.extend(window._formBuilderActions, {
		actionAdjustPaymentAmount: function(actionData, result) {
			// find the target field
			var target = $(actionData.selector)[0];
			if (target !== undefined) {
				this._preparePaymentField(target, actionData);
					// add this adjustment if the conditions are true
				target._formBuilderAdjustments.adjustments[actionData.id] = result ? actionData.amount.adjust : 0;
				this._calculateTotalCharge(target);
			}
			return this;
		},
		actionSetPaymentAmount: function(actionData, result) {
			// find the target field
			var target = $(actionData.selector)[0];
			if (target !== undefined) {
				this._preparePaymentField(target, actionData);
				if (result) {
					// assign the total to the input
					target._formBuilderAdjustments.override = actionData.amount.adjust;
				} else {
					target._formBuilderAdjustments.override = undefined;
				}
				this._calculateTotalCharge(target);
			}
			return this;
		},
		_preparePaymentField: function(field, actionData) {
			if (field._formBuilderAdjustments === undefined) {
				field._formBuilderAdjustments = {
					_default: parseFloat(actionData.amount._default),
					adjustments: {}
				};
			}
			return field;
		},
		_calculateTotalCharge: function(field) {
			if (field._formBuilderAdjustments.override !== undefined) {
				$(field).val(field._formBuilderAdjustments.override.toFixed(2)).trigger('change');
				return this;
			}
			clearTimeout(field._formBuilderAdjustments.timer);
			setTimeout(function() {
				var values = Object.values(field._formBuilderAdjustments.adjustments);
				field._formBuilderAdjustments.amount = field._formBuilderAdjustments._default;
				// calculate the total of all conditions
				for(var k=0; k < values.length; k++) {
					field._formBuilderAdjustments.amount += parseFloat(values[k]);
				}
				$(field).val(field._formBuilderAdjustments.amount.toFixed(2)).trigger('change');
			}, 100);
			return this;
		}
	});
}(jQuery));