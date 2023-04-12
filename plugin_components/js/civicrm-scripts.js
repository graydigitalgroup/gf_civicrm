/* global gform */
;(function ($) {
	window.CountryStateSelect = function (formId, countryFieldId, stateFieldId, defaultStateId) {
		var self = this

		self.formId = parseInt(formId)
		self.countryFieldId = countryFieldId
		self.stateFieldId = stateFieldId
		self.defaultStateId = defaultStateId

		self.init = function () {
			// eslint-disable-next-line no-unused-vars
			gform.addAction('gform_input_change', function (elem, formId, fieldId) {
				var selects = $('select[id^="input_' + self.formId + '_' + self.countryFieldId + '"]')
				if (self.formId == parseInt(formId) && selects.index(elem) != -1) {
					var selectedCountryId = $(elem).val()
					var countryElementId = $(elem).attr('id')
					var instance = countryElementId.split('-')[1]

					self.filterStates(selectedCountryId, instance)
				}
			})

			gform.addAction('gform_repeater_post_item_add', function (clone) {
				var countrySelects = $(clone).find(
					'select[id^="input_' + self.formId + '_' + self.countryFieldId + '"]'
				)
				$(countrySelects).each(function () {
					$(this)
						.children('option[value="' + self.defaultStateId.split('_')[0] + '"]')
						.eq(0)
						.prop('selected', true)
				})
				var stateSelects = $(clone).find('select[id^="input_' + self.formId + '_' + self.stateFieldId + '"]')
				$(stateSelects).each(function () {
					$(this)
						.children('option[value="' + self.defaultStateId + '"]')
						.eq(0)
						.prop('selected', true)
				})
				return clone
			})

			$('select[id^="input_' + self.formId + '_' + self.countryFieldId + '"]').each(function () {
				var selectedCountryId = $(this).val()
				var countryElementId = $(this).attr('id')
				var instance = countryElementId.split('-')[1]
				$(this).data('originalId', selectedCountryId)

				var stateField = $('#input_' + self.formId + '_' + self.stateFieldId + '-' + instance)
				var defaultSelect = $(stateField).children('option:selected').val()
				defaultSelect = '' === defaultSelect ? self.defaultStateId : defaultSelect
				$(stateField).data('originalId', defaultSelect)

				self.filterStates(selectedCountryId, instance)
			})
		}

		self.filterStates = function (selectedCountryId, instance) {
			var statesField = $('#input_' + self.formId + '_' + self.stateFieldId + '-' + instance)
			var defaultOption = $(statesField).data('originalId')

			$(statesField)
				.children('option')
				.each(function () {
					$(this).val().startsWith(selectedCountryId)
						? $(this).removeAttr('disabled').show()
						: $(this).attr('disabled', 'disabled').hide()
				})

			if (0 === $(statesField).children('option:enabled:selected').length) {
				if (0 === $(statesField).children('option[value="' + defaultOption + '"]:enabled').length) {
					$(statesField).children('option:enabled').eq(0).prop('selected', true)
				} else {
					$(statesField)
						.children('option[value="' + defaultOption + '"]')
						.eq(0)
						.prop('selected', true)
				}
			}
		}

		self.init()
	}
})(jQuery)
