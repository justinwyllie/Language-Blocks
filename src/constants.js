const settings = {
    domain:
    {
        type: "slashes", /* query or slashes */
        domainForUsers: "https://justinwyllie.github.io",
    },
    site: "repititor",
    defaultUserLang: "en"
}

const SHOWLOGIN = true;

const LABELS =  {
    ru: 
    {
        copied_to_clipboard: {nominative: "copied to clipboard"}
    },
    en:
    {
        copied_to_clipboard: {nominative: "copied to clipboard"}
    }
}

export {
    settings,
    LABELS,
    SHOWLOGIN
}