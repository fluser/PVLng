##############################################################################
### @author      Knut Kohl <github@knutkohl.de>
### @copyright   2012-2013 Knut Kohl
### @license     GNU General Public License http://www.gnu.org/licenses/gpl.txt
### @version     $Id$
##############################################################################

##############################################################################
### Actual/last value
##############################################################################
function twitter_last {
	value=$(PVLngGET2 "data/$1.tsv?period=last" | cut -f2)
	log 1 "$url => $value"
	echo $value
}

##############################################################################
### Average value over the last $1 minutes (e.g. 60 for last hour)
### $1 - Start time
### $2 - Period for aggregation
### $3 - GUID
### Example params: 00:00 24hours
### Start at today midnight and aggregate 24 hours > 1 row as result
##############################################################################
function twitter_average {
	value=$(PVLngGET2 "data/$3.tsv?start=$1&period=$2" | cut -f2)
	log 1 "$url => $value"
	echo $value
}

##############################################################################
### Max. value of today
##############################################################################
function twitter_maximum {
	PVLngGET2 "data/$1.tsv" >$TMPFILE

	max=0
	while read line; do
		value=$(echo "$line" | cut -f2)
		test $value -gt $max && max=$value
	done <$TMPFILE

	log 1 "$url => $max"
	echo $max
}

##############################################################################
### Production today in kWh
##############################################################################
function twitter_today {
	value=$(PVLngGET2 "data/$1.tsv?period=last" | cut -f2)
	log 1 "$url => $value"
	echo $value
}

##############################################################################
### Overall production in MWh
##############################################################################
function twitter_overall {
	value=$(PVLngGET2 "data/$1.tsv?start=0&period=99y" | cut -f2)
	log 1 "$url => $value"
	echo $value
}

##############################################################################
### Today working hours in hours :-)
##############################################################################
function twitter_today_working_hours {
	PVLngGET2 "data/$1.tsv" >$TMPFILE

	### get first line, get 1st value
	min=$(cat $TMPFILE | head -n1 | cut -f1)
	### get last line, get 1st value
	max=$(cat $TMPFILE | tail -n1 | cut -f1)
	log 1 "$url => $min - $max"

	### to hours
	echo "scale=3; ($max - $min) / 3600" | bc -l
}
