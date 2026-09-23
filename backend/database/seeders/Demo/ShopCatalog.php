<?php

namespace Database\Seeders\Demo;

use Database\Seeders\Demo\TrueHours as H;

/**
 * Qumështorja Dajti's 75 customers around Tirana, grouped by the round that serves them.
 *
 * Each row: name, street, round (V = Veri, Q = Qendër, L = Lindje), town, true hours, what the
 * shop declared (exact / wrong = claims standard hours / unknown / weekdays = only Mon–Fri known),
 * contact person, and whether the owner is often away on Monday and Thursday mornings.
 */
final class ShopCatalog
{
    public const SHOPS = [
        ['Market Ardi', 'Rruga e Dibrës', 'V', 'Tiranë', H::LATE_MONDAY, 'wrong', 'Ardian Hoxha', false],
        ['Furra Lulzimi', 'Rruga e Dibrës', 'V', 'Tiranë', H::EARLY, 'exact', 'Lulzim Kola', false],
        ['Market Kthesa', 'Rruga e Durrësit', 'V', 'Tiranë', H::STANDARD, 'exact', 'Gëzim Leka', false],
        ['Ushqimore Besa', 'Rruga Hoxha Tahsim', 'V', 'Tiranë', H::LATE, 'wrong', 'Besa Marku', false],
        ['Market Stacioni', 'Rruga Karl Gega', 'V', 'Tiranë', H::STANDARD, 'exact', 'Arben Çela', false],
        ['Minimarket Laprakë', 'Rruga Dritan Hoxha', 'V', 'Tiranë', H::LATE_MONDAY, 'wrong', 'Fatos Doda', false],
        ['Market Eni', 'Rruga Siri Kodra', 'V', 'Tiranë', H::BANK_MORNINGS, 'wrong', 'Eni Lala', false],
        ['Market Drini', 'Rruga Siri Kodra', 'V', 'Tiranë', H::STANDARD, 'unknown', 'Petrit Gjini', false],
        ['Dyqani Flora', 'Rruga 5 Maji', 'V', 'Tiranë', H::LATE_MONDAY, 'exact', 'Flora Shehu', false],
        ['Market Tomorri', 'Rruga Bardhok Biba', 'V', 'Tiranë', H::STANDARD, 'exact', 'Kujtim Tafa', true],
        ['Pastiçeri Mona', 'Rruga e Dibrës', 'V', 'Tiranë', H::EARLY, 'exact', 'Mona Ruka', false],
        ['Market Ylli', 'Rruga Thanas Ziko', 'V', 'Tiranë', H::LATE, 'wrong', 'Ylli Bushati', false],
        ['Market Kodra', 'Rruga Hamdi Sina', 'V', 'Tiranë', H::STANDARD, 'weekdays', 'Dritan Kodra', false],
        ['Market Arbëri', 'Rruga Irfan Tomini', 'V', 'Tiranë', H::SATURDAY_EARLY, 'exact', 'Arbër Selimi', false],
        ['Market Gëzimi', 'Rruga Muhamet Gjollesha', 'V', 'Tiranë', H::STANDARD, 'exact', 'Gëzim Hysa', false],
        ['Market Liria', 'Rruga Asim Vokshi', 'V', 'Tiranë', H::LATE_MONDAY, 'wrong', 'Liri Bega', false],
        ['Ushqimore Nisi', 'Rruga Kongresi i Manastirit', 'V', 'Tiranë', H::STANDARD, 'unknown', 'Nexhat Isufi', false],
        ['Market Horizont', 'Rruga Todi Shkurti', 'V', 'Tiranë', H::BANK_MORNINGS, 'exact', 'Sokol Hoxhaj', false],
        ['Market Qafa', 'Rruga e Paskuqanit', 'V', 'Paskuqan', H::LATE, 'exact', 'Qazim Cani', false],
        ['Market Fresk', 'Rruga e Paskuqanit', 'V', 'Paskuqan', H::STANDARD, 'exact', 'Mirela Ndoja', true],
        ['Market Luli', 'Rruga Kodra e Diellit', 'V', 'Paskuqan', H::STANDARD, 'exact', 'Luljeta Pepa', false],
        ['Dyqani i Lagjes', 'Rruga Fushë Kosova', 'V', 'Paskuqan', H::STANDARD, 'weekdays', 'Agim Nika', false],
        ['Market 2 Vëllezërit', 'Rruga e Paskuqanit', 'V', 'Paskuqan', H::STANDARD, 'unknown', 'Ilir Dervishi', false],
        ['Market Kristi', 'Rruga Hoxha Tahsim', 'V', 'Tiranë', H::STANDARD, 'exact', 'Kristi Meta', false],
        ['Market Anxhela', 'Rruga e Durrësit', 'V', 'Tiranë', H::STANDARD, 'exact', 'Anxhela Koçi', false],
        ['Market Blini', 'Rruga Karl Gega', 'V', 'Tiranë', H::STANDARD, 'exact', 'Blin Rama', false],

        ['Market Alba', 'Rruga e Kavajës', 'Q', 'Tiranë', H::LATE_MONDAY, 'wrong', 'Alban Duka', false],
        ['Supermarket Joni', 'Rruga e Kavajës', 'Q', 'Tiranë', H::STANDARD, 'exact', 'Jonid Hasa', false],
        ['Bar-Kafe Vila', 'Rruga Ismail Qemali', 'Q', 'Tiranë', H::EARLY, 'exact', 'Vilma Gjoni', false],
        ['Market Sara', 'Rruga Pjetër Bogdani', 'Q', 'Tiranë', H::LATE, 'exact', 'Sara Prifti', false],
        ['Market Te Ura', 'Rruga Myslym Shyri', 'Q', 'Tiranë', H::FRIDAY_CLOSED, 'wrong', 'Adem Rexha', false],
        ['Ushqimore Tefta', 'Rruga Sami Frashëri', 'Q', 'Tiranë', H::LUNCH, 'wrong', 'Tefta Kurti', false],
        ['Market Kevin', 'Rruga Vaso Pasha', 'Q', 'Tiranë', H::STANDARD, 'exact', 'Kevin Zeqiri', false],
        ['Market Denis', 'Rruga Mustafa Matohiti', 'Q', 'Tiranë', H::BANK_MORNINGS, 'wrong', 'Denis Mullai', false],
        ['Pastiçeri Lulishtja', 'Rruga Ibrahim Rugova', 'Q', 'Tiranë', H::EARLY, 'exact', 'Lule Beqiri', false],
        ['Market Iris', 'Rruga Brigada VIII', 'Q', 'Tiranë', H::LATE_MONDAY, 'exact', 'Iris Dema', false],
        ['Market Megi', 'Rruga Kont Urani', 'Q', 'Tiranë', H::STANDARD, 'exact', 'Megi Sulaj', true],
        ['Market Ermal', 'Rruga Haxhi Hysen Dalliu', 'Q', 'Tiranë', H::FRIDAY_PRAYER, 'exact', 'Ermal Hoxha', false],
        ['Market Ana', 'Rruga e Barrikadave', 'Q', 'Tiranë', H::STANDARD, 'unknown', 'Ana Lika', false],
        ['Ushqimore Fatmiri', 'Rruga Fortuzi', 'Q', 'Tiranë', H::LUNCH, 'exact', 'Fatmir Çuni', false],
        ['Market Deni', 'Rruga Mine Peza', 'Q', 'Tiranë', H::SATURDAY_EARLY, 'exact', 'Deni Gjoka', false],
        ['Market Stela', 'Rruga Qemal Stafa', 'Q', 'Tiranë', H::LATE_MONDAY, 'wrong', 'Stela Brahja', false],
        ['Market Agimi', 'Rruga Luigj Gurakuqi', 'Q', 'Tiranë', H::STANDARD, 'weekdays', 'Agim Rrapaj', false],
        ['Market Rozafa', 'Rruga Shyqyri Berxolli', 'Q', 'Tiranë', H::BANK_MORNINGS, 'exact', 'Roza Tahiri', false],
        ['Market Dea', 'Rruga Sulejman Pasha', 'Q', 'Tiranë', H::LATE, 'exact', 'Dea Ndreu', false],
        ['Market Noa', 'Rruga e Kavajës', 'Q', 'Tiranë', H::LUNCH, 'exact', 'Noel Kapaj', false],
        ['Market Deti', 'Rruga e Kasharit', 'Q', 'Kashar', H::STANDARD, 'exact', 'Artan Shkurti', false],
        ['Market Yzberishi', 'Rruga e Kasharit', 'Q', 'Kashar', H::STANDARD, 'exact', 'Bujar Gashi', true],
        ['Market Kombinati', 'Rruga Teodor Keko', 'Q', 'Kashar', H::STANDARD, 'unknown', 'Klajdi Muça', false],
        ['Dyqani Mira', 'Rruga e Kasharit', 'Q', 'Kashar', H::SATURDAY_EARLY, 'exact', 'Mira Tole', false],
        ['Ushqimore Kashari', 'Rruga e Pezës', 'Q', 'Kashar', H::STANDARD, 'unknown', 'Xhevdet Laçi', false],
        ['Market Vlora', 'Rruga Andon Zako Çajupi', 'Q', 'Tiranë', H::STANDARD, 'weekdays', 'Vladimir Qose', false],

        ['Bulmetore Gjirokastra', 'Rruga e Elbasanit', 'L', 'Tiranë', H::LUNCH, 'wrong', 'Sotir Kote', false],
        ['Market Porcelani', 'Rruga e Porcelanit', 'L', 'Tiranë', H::LUNCH, 'exact', 'Mimoza Lama', false],
        ['Market Elona', 'Rruga Sulejman Delvina', 'L', 'Tiranë', H::STANDARD, 'exact', 'Elona Bardhi', false],
        ['Furra Artan', 'Rruga Medar Shtylla', 'L', 'Tiranë', H::EARLY, 'exact', 'Artan Vata', false],
        ['Market Dajti', 'Rruga e Elbasanit', 'L', 'Tiranë', H::LUNCH, 'wrong', 'Ramadan Hoti', false],
        ['Market Bledi', 'Rruga Faik Konica', 'L', 'Tiranë', H::FRIDAY_PRAYER, 'wrong', 'Bledar Kaçi', false],
        ['Ushqimore Alketa', 'Rruga Arben Broci', 'L', 'Tiranë', H::LUNCH, 'exact', 'Alketa Shima', false],
        ['Market Tirana e Re', 'Rruga Frosina Plaku', 'L', 'Tiranë', H::STANDARD, 'exact', 'Endrit Bala', true],
        ['Market Nikolla', 'Rruga Nikolla Lena', 'L', 'Tiranë', H::LUNCH, 'wrong', 'Nikolin Gjeta', false],
        ['Market Feriti', 'Rruga Ferit Xhajko', 'L', 'Tiranë', H::LATE_MONDAY, 'exact', 'Ferit Muka', false],
        ['Pastiçeri Ëmbëlsira', 'Rruga e Elbasanit', 'L', 'Tiranë', H::EARLY, 'exact', 'Suela Kamberi', false],
        ['Market Petriti', 'Rruga Tish Daija', 'L', 'Tiranë', H::LUNCH, 'exact', 'Petrit Allko', false],
        ['Market Liqeni', 'Rruga Dervish Hima', 'L', 'Tiranë', H::STANDARD, 'unknown', 'Gentian Lleshi', false],
        ['Market Selvia', 'Rruga e Elbasanit', 'L', 'Tiranë', H::FRIDAY_PRAYER, 'exact', 'Selvi Peci', false],
        ['Market Kalaja', 'Rruga Gjergj Legisi', 'L', 'Tiranë', H::LUNCH, 'wrong', 'Mentor Kalaja', false],
        ['Market Kika', 'Rruga Pjetër Budi', 'L', 'Tiranë', H::LATE, 'exact', 'Kristina Kika', false],
        ['Market Sauku', 'Rruga e Saukut', 'L', 'Tiranë', H::SATURDAY_EARLY, 'exact', 'Arjan Sauku', false],
        ['Market Farka', 'Rruga e Farkës', 'L', 'Farkë', H::LUNCH, 'exact', 'Fatbardha Leka', false],
        ['Ushqimore Lumi', 'Rruga e Farkës', 'L', 'Farkë', H::STANDARD, 'exact', 'Lumturi Deda', true],
        ['Market Mali', 'Rruga e Lundrës', 'L', 'Farkë', H::LATE_MONDAY, 'exact', 'Mal Gjoni', false],
        ['Market Pema', 'Rruga e Farkës', 'L', 'Farkë', H::LUNCH, 'exact', 'Pranvera Zefi', false],
        ['Market Ujëvara', 'Rruga Asim Zeneli', 'L', 'Tiranë', H::STANDARD, 'unknown', 'Ujkan Dika', false],
        ['Market Rinia', 'Rruga Hysen Hoxha', 'L', 'Tiranë', H::STANDARD, 'weekdays', 'Rinor Hasani', false],
    ];

    /** Where each round's shops lie: [lat min, lat max, lng min, lng max] per round and town. */
    public const AREAS = [
        'V' => ['Tiranë' => [41.3345, 41.3490, 19.7960, 19.8310], 'Paskuqan' => [41.3490, 41.3560, 19.8050, 19.8260]],
        'Q' => ['Tiranë' => [41.3130, 41.3320, 19.7980, 19.8230], 'Kashar' => [41.3200, 41.3330, 19.7800, 19.7950]],
        'L' => ['Tiranë' => [41.3150, 41.3380, 19.8230, 19.8460], 'Farkë' => [41.3100, 41.3180, 19.8380, 19.8500]],
    ];

    /** Directions drivers left for each other, by shop name. */
    public const ACCESS_NOTES = [
        'Market Ardi' => 'Hyrja për furnizim nga rruga anësore, pranë garazhit.',
        'Supermarket Joni' => 'Parko te pompa e benzinës; rruga para dyqanit është e ngushtë.',
        'Bulmetore Gjirokastra' => 'Frigoriferi i qumështit është në fund, majtas. Kthe kosin e skaduar.',
        'Market Te Ura' => 'Shkarko para orës 10:00, pastaj rrugën e bllokon tregu.',
        'Market Tomorri' => 'Telefono 5 minuta para se të arrish.',
        'Pastiçeri Lulishtja' => 'Ajkën e lë në frigoriferin e pasmë, çelësi te banaku.',
        'Market Qafa' => 'Qeni në oborr është i lidhur. I bjer ziles dy herë.',
        'Market Porcelani' => 'Mos e lër mallin jashtë, e merr dielli pasdite.',
        'Market Megi' => 'Faturën e firmos vetëm pronari.',
        'Market Farka' => 'Rruga e fundit pa asfalt: kujdes me furgonin pas shiut.',
        'Ushqimore Tefta' => 'Hyrja nga oborri i pallatit, porta jeshile.',
        'Market Dajti' => 'Merr arkat bosh të javës së kaluar.',
    ];
}
