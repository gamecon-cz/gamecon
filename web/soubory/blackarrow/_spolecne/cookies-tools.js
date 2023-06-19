/**
 * @param {string} name
 */
function expireActiveCookies(name) {
  var pathname = location.pathname.replace(/\/$/, ''),
    segments = pathname.split('/'),
    paths = [];

  for (var i = 0, l = segments.length, path; i < l; i++) {
    path = segments.slice(0, i + 1).join('/');

    paths.push(path);       // as file
    paths.push(path + '/'); // as directory
  }

  expireAllCookies(name, paths);
}

/**
 * @param {string} name
 * @param {array<string>} paths
 */
function expireAllCookies(name, paths) {
  var expires = new Date(0).toUTCString();

  // expire null-path cookies as well
  document.cookie = name + '=; expires=' + expires;

  for (var i = 0, l = paths.length; i < l; i++) {
    document.cookie = name + '=; path=' + paths[i] + '; expires=' + expires;
  }
}

function setCookie(name, value) {
  document.cookie = `${name}=${value};path=${document.baseURI};SameSite=Strict`;
}

function deleteCookie(name) {
  expireActiveCookies(name)
}

function getCookie(name) {
  var cookie = document.cookie.split('; ').find((row) => row.startsWith(`${name}=`))
  if (!cookie) {
    return null;
  }
  return cookie.split('=').at(1)
}
