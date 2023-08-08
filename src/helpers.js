const EnforceApostrophePolicy = (string) => {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

const CapitalizeFirstLetter = (string) => {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

export  {
    
    EnforceApostrophePolicy,
    CapitalizeFirstLetter 

};