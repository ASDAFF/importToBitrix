/**
 * Created by andkon on 27.01.14.
 */
function setSubmit(select) {
    var date = new Date();
    var tmp_link = '';
    switch ($(select).val()) {
        case "7":
            //последние 7 дней
            tmp_link = date.getDate() + '.' + (date.getMonth() + 1) + '.' + date.getFullYear();
            date.setDate(date.getDate() - 7);
            tmp_link = date.getDate() + '.' + (date.getMonth() + 1) + '.' + date.getFullYear() + '-' + tmp_link;
            break;
        case "30":
            //последние 30 дней
            tmp_link = date.getDate() + '.' + (date.getMonth() + 1) + '.' + date.getFullYear();
            date.setDate(date.getDate() - 30);
            tmp_link = date.getDate() + '.' + (date.getMonth() + 1) + '.' + date.getFullYear() + '-' + tmp_link;
            break;
        case "month":
            //текущий месяц
            tmp_link = date.getDate() + '.' + (date.getMonth() + 1) + '.' + date.getFullYear();
            date.setDate(1);
            tmp_link = date.getDate() + '.' + (date.getMonth() + 1) + '.' + date.getFullYear() + '-' + tmp_link;
            break;
        case "-1":
            //прошедший месяц
            date.setDate(0);
            tmp_link = date.getDate() + '.' + (date.getMonth() + 1) + '.' + date.getFullYear();
            date.setDate(1);
            tmp_link = date.getDate() + '.' + (date.getMonth() + 1) + '.' + date.getFullYear() + '-' + tmp_link;
            break;
        case "up":
            //последний 1 апдейт
            tmp_link = '&up=1';
            break;
        case "up10":
            //последние 10 апдейтов
            tmp_link = '&up=10'
            break;
        case "up30":
            //последние 30 апдейтов
            tmp_link = '&up=30'
            break;
        case "period":
            //произвольный период
            $("#fields_period").show('fast');
            return;
            break;
        case "check":
            //последние 10 проверок
            tmp_link = '&check=10';
            break;
    }

    $("#fields_period").hide('fast');
    redirect(tmp_link, select);
}

function redirect(tmp_link, select) {
    tmp_link += '&per_sel=' + $(select).val();
    var $get = getQueryParam();
    tmp_link = window.location.pathname + '?project=' + $get['project'] + '&token=' + $get['token']
        + '&period=' + tmp_link;
    window.location.href = tmp_link;
}

function getTdForChangeInTop(up) {
    var tds = [];
    var tmp = $('.high').add('.mid');
    for (var i = 0; i < tmp.length; i++) {
        var curr = parseInt($(tmp[i]).html());
        var prev = parseInt($(tmp[i]).prev().html());
        var next = parseInt($(tmp[i]).next().html());
        if (curr <= 10 || prev <= 10 || next <= 10) {
            if ((prev != curr) || curr != next) {
                if (up && curr <= 10) {
                    tds.push(tmp[i]);
                }

                if (!up && curr > 10) {
                    tds.push(tmp[i]);
                }
            }
        }
    }

    return tds;
}

function getQueryParam() {
    var tmp = window.location.search.replace('?', '').split('&');
    var $get = [];
    for (i in tmp) {
        var ii = tmp[i].split('=');
        $get[ii[0]] = ii[1];
    }

    return $get;
}

$(function () {
    var $get = getQueryParam();
    if ($get['per_sel'] != undefined) {
        $('#Period option[value="' + $get['per_sel'] + '"]').attr('selected', 'selected');
        if ($get['per_sel'] == 'period') {
            var tmp = $get['period'].split('-');
            $("#begin").val(tmp[0]);
            $("#end").val(tmp[1]);
            $("#fields_period").show(0);
        }
    } else {
        $('#Period option:first').attr('selected', 'selected');
    }

    $.datepicker.setDefaults($.extend($.datepicker.regional["ru"]));

    $("#begin").datepicker({
        defaultDate: "-1w",
        locale: 'ru-RU',
        changeMonth: true,
        numberOfMonths: 1,
        dateFormat: 'dd.mm.yy',
        onClose: function (selectedDate) {
            $("#end").datepicker("option", "minDate", selectedDate);
        }
    });
    $("#end").datepicker({
        changeMonth: true,
        locale: 'ru-RU',
        numberOfMonths: 1,
        dateFormat: 'dd.mm.yy',
        onClose: function (selectedDate) {
            $("#begin").datepicker("option", "maxDate", selectedDate);
        }
    });
    $("#send").on('click', function () {
        redirect('&period=' + $('#begin').val() + '-' + $('#end').val(), $('#Period'));
    });
    $('.top_checkbox input[value=1]').on('change', function () {
        if (this.checked) {
            $('.high').addClass('top_10');
        } else {
            $('.high').removeClass('top_10');
        }
    });
    $('.top_checkbox input[value=2]').on('change', function () {
        if (this.checked) {
            $('.mid').addClass('top_50');
        } else {
            $('.mid').removeClass('top_50');
        }
    });
    $('.top_checkbox input[value=3]').on('change', function () {
        if (this.checked) {
            $('.low').addClass('top_100');
        } else {
            $('.low').removeClass('top_100');
        }
    });
    $('.top_checkbox input[value=4]').on('change', function () {
        if (this.checked) {
            var tds = getTdForChangeInTop(true);
            $(tds).addClass('top_10_inOrOut upPos');
            var tds = getTdForChangeInTop(false);
            $(tds).addClass('top_10_inOrOut downPos');
        } else {
            var tds = getTdForChangeInTop(true);
            $(tds).removeClass('top_10_inOrOut');
            $(tds).removeClass('upPos');
            var tds = getTdForChangeInTop(false);
            $(tds).removeClass('top_10_inOrOut');
            $(tds).removeClass('downPos');
        }
    });
});