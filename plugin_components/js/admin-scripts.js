/* global gform, ToggleConditionalLogic */
export function PopulateCiviCRMCondition(args) {
	var self = this

	self.strings = typeof args.strings != 'undefined' ? args.strings : {}
	self.logicObject = args.logicObject
	self.objectType = args.objectType

	self.init = function () {
		gform.addFilter('gform_conditional_object', function (object, objectType) {
			if (objectType != self.objectType) {
				return object
			}

			return self.logicObject
		})
		// eslint-disable-next-line no-unused-vars
		gform.addFilter('gform_conditional_logic_description', function (description, descPieces, objectType, obj) {
			if (objectType != self.objectType) {
				return description
			}

			descPieces.actionType = descPieces.actionType.replace('<select', '<select style="display:none;"')
			descPieces.objectDescription = self.strings.objectDescription
			var descPiecesArr = self.makeArray(descPieces)

			return descPiecesArr.join(' ')
		})

		jQuery(document).ready(function () {
			ToggleConditionalLogic(true, self.objectType)
		})

		jQuery('input#' + self.objectType + '_conditional_logic')
			.parents('form')
			.on('submit', function () {
				jQuery('input#' + self.objectType + '_conditional_logic_object').val(JSON.stringify(self.logicObject))
			})
	}

	self.makeArray = function (object) {
		var array = []
		for (const i in object) {
			array.push(object[i])
		}
		return array
	}

	self.isSet = function ($var) {
		return typeof $var != 'undefined'
	}

	return self.init()
}

window.PopulateCiviCRMCondition = PopulateCiviCRMCondition
