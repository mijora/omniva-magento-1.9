function callOmniva(url){
    if (confirm('Svarbu! Vėliausias galimas kurjerio iškvietimas yra iki 15val. Vėliau iškvietus kurjerį negarantuojame, jog siunta bus paimta.')) {
        setLocation(url);
    }
}