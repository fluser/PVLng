/**
 *
 *
 * @author      Knut Kohl <github@knutkohl.de>
 * @copyright   2012-2013 Knut Kohl
 * @license     GNU General Public License http://www.gnu.org/licenses/gpl.txt
 * @version     $Id$
 */

$(function() {

	$('#table-info').DataTable({
		bJQueryUI: true,
		bPaginate: false,
		bLengthChange: false,
		bFilter: false,
		bSort: false,
		bInfo: false
	});

	$('#regenerate').click(function() {
	    $(this).val("{{Sure}}?").addClass("b").unbind();
	    return false;
	});

	$('#table-stats').DataTable({
		bJQueryUI: true,
		bPaginate: false,
		bLengthChange: false,
		bFilter: false,
		bInfo: false,
		aaSorting: [[1, 'asc']],
		aoColumnDefs: [
			{ bSortable: false, aTargets: [ 0 ] },
			{ sType: 'numeric-' + (('{LANGUAGE}' == 'de') ? 'comma' : 'dot'), aTargets: [5] }
		]
	});

});
