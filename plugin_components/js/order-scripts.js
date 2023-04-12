/*global gform */
export function GCCiviCRM_OrderMap(options) {
	var self = this
	var $ = jQuery

	self.options = options
	self.UI = jQuery('#gaddon-setting-row-' + self.options.fieldName)

	self.init = function () {
		self.bindEvents()
		self.setupData()
		self.setupRepeater()
	}
	self.bindEvents = function () {
		self.UI.on('change', 'select[name="_gaddon_setting_' + self.options.fieldName + '"]', function () {})

		self.UI.on('change', '.price-field-check input[type="checkbox"]', function () {
			var parent = $(this).parents('.gaddon-section-setting-' + self.options.fieldName)
			$(parent).find('.price-field-fixed').toggleClass('hidden')
			$(parent).find('.price-field').toggleClass('hidden')
		})

		self.UI.on('change', 'select.entity_val', function () {
			var val = jQuery(this).val()
			var parent = $(this).parents('.gaddon-section-setting-' + self.options.fieldName)
			if (val === 'contribution') {
				jQuery(parent).find('.price-other-check').removeClass('hidden')
				jQuery(parent)
					.find('.gaddon-section-setting-' + self.options.otherFieldId)
					.removeClass('hidden')
				jQuery(parent).find('.gaddon-section-setting-entity_data label span.required').addClass('hidden')
				if (jQuery(parent).find('.entity_data').siblings('span.gf_tooltip').length > 0) {
					jQuery(parent)
						.find('.entity_data')
						.siblings('span.gf_tooltip')
						.children('.gf_invalid')
						.addClass('hidden')
				}
			} else {
				jQuery(parent).find('.price-other-check').addClass('hidden')
				jQuery(parent)
					.find('.gaddon-section-setting-' + self.options.otherFieldId)
					.addClass('hidden')
				jQuery(parent).find('.gaddon-section-setting-entity_data label span.required').removeClass('hidden')
				if (jQuery(parent).find('.entity_data').siblings('span.gf_tooltip').length > 0) {
					jQuery(parent)
						.find('.entity_data')
						.siblings('span.gf_tooltip')
						.children('.gf_invalid')
						.removeClass('hidden')
				}
			}
		})
	}

	self.setupData = function () {
		var data = jQuery('#' + self.options.fieldId).val()
		self.data = data ? jQuery.parseJSON(data) : null

		if (!self.data) {
			self.data = [
				{
					entity_val: '',
					entity_data_val: '',
					payment_option_val: '',
					payment_fixed_check_val: '',
					price_set_option_val: '',
					other_check_val: '',
					other_amount_val: '',
				},
			]
		}
	}

	self.setupRepeater = function () {
		var limit = self.options.limit > 0 ? self.options.limit : 0

		self.UI.find('.settings-repeater').repeater({
			limit: limit,
			items: self.data,
			addButtonMarkup: '<span class="button">Add Item</span>',
			removeButtonMarkup: '<span class="button">Remove Item</span>',
			callbacks: {
				beforeAdd: function (obj, $elem, item, index) {
					jQuery($elem)
						.find('[id]')
						.each(function (i, el) {
							jQuery(el).attr('id', jQuery(el).attr('id') + '_' + index)
						})
				},
				add: function (obj, $elem, item, index) {
					var elem = self.UI.find('.settings-repeater')
					for (var property in item) {
						if (!Object.prototype.hasOwnProperty.call(item, property)) {
							continue
						}
						var input = elem.find('.' + property + '_' + index)
						if (input.is('select')) {
							continue
						}
						input.val(item[property])
						var parent = $(input).parents('.gaddon-section-setting-' + self.options.fieldName)

						if (
							input[0] instanceof HTMLInputElement &&
							input[0].getAttribute('type') == 'checkbox' &&
							property === 'payment_fixed_check_val'
						) {
							if ($(input).is(':checked')) {
								$(parent).find('.price-field-fixed').removeClass('hidden')
								$(parent).find('.price-field').addClass('hidden')
							} else {
								$(parent).find('.price-field-fixed').addClass('hidden')
								$(parent).find('.price-field').removeClass('hidden')
							}
						}

						if (property === 'entity_data_val') {
							var entity = $(parent).find('.entity_val')
							if (
								(entity && $(entity).val() !== 'contribution' && item[property] !== '') ||
								(entity && $(entity).val() === 'contribution')
							) {
								if ($(input).siblings('span.gf_tooltip').children('.gf_invalid').length > 0) {
									$(input).siblings('span.gf_tooltip').children('.gf_invalid').addClass('hidden')
								}
							}
							if (entity && $(entity).val() === 'contribution') {
								jQuery(parent)
									.find('.gaddon-section-setting-entity_data label span.required')
									.addClass('hidden')
							}
						}
					}

					if (Object.prototype.hasOwnProperty.call(window, 'gform')) {
						gform.doAction('gform_fieldmap_add_row', obj, $elem, item)
					}
				},
				save: function (obj, data) {
					jQuery('#' + self.options.fieldId).val(JSON.stringify(data))
				},
			},
		})
	}

	return self.init()
}

window.GCCiviCRM_OrderMap = GCCiviCRM_OrderMap
