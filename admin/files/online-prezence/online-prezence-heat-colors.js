/**
 * Vypůjčený https://www.npmjs.com/package/temp-color
 *
 * Function takes four parameters:
 *
 *     t - value that will be scaled into RGB
 *     min - lowest possible value (scale begins there)
 *     max - highest possible value (scale ends there)
 *     mode - OPTIONAL
 *
 * Mode
 *
 *     default - scaling from blue to red -> no need to provide any additional parameters
 *     extended - scaling from violet-blue to violet-red -> add 'extended' parameter at the end
 *     half - scaling from green to red (from good to bad); no blue colors -> add 'half' parameter at the end
 *
 * Function returns an object with calculated RGB values
 */

function checkRange(value) {
  if (value <= 0) {
    return 0
  }
  if (value > 255) {
    return 255
  }
  return value
}

/**
 * @param {number} temperature
 * @param {number} min
 * @param {number} max
 * @param {string} mode default, extended, half
 * @return {{r: (number|*), b: (number|*), g: (number|*)}}
 */
function tempToColor(temperature, min, max, mode) {
  if (!Number.isFinite(temperature) || !Number.isFinite(min) || !Number.isFinite(max)) {
    throw new TypeError('function tempToColor() expected only numbers')
  }

  if (min > max) {
    throw new Error('minimum cannot be greater than maximum')
  }

  if (temperature < min) {
    temperature = min
  } else if (temperature > max) {
    temperature = max
  }

  const nT = (temperature - min) / (max - min)
  let rValue = 255
  let gValue = 255
  let bValue = 255

  switch (mode) {
    case 'extended': {
      const regions = [1 / 6, (1 / 6) * 2, (1 / 6) * 3, (1 / 6) * 4, (1 / 6) * 5]
      if (nT <= regions[0]) {
        rValue = 128 - 6 * nT * 127.999
        gValue = 0
        bValue = 255
      } else if (nT > regions[0] && nT <= regions[1]) {
        rValue = 0
        gValue = 1280 - 6 * (1 - nT) * 255.999
        bValue = 255
      } else if (nT > regions[1] && nT <= regions[2]) {
        rValue = 0
        gValue = 255
        bValue = 768 - 6 * nT * 255.999
      } else if (nT > regions[2] && nT <= regions[3]) {
        rValue = 768 - 6 * (1 - nT) * 255.999
        gValue = 255
        bValue = 0
      } else if (nT > regions[3] && nT <= regions[4]) {
        rValue = 255
        gValue = 1280 - 6 * nT * 255.999
        bValue = 0
      } else {
        rValue = 255
        gValue = 0
        bValue = 128 - 6 * (1 - nT) * 127.999
      }
      break
    }

    case 'half': {
      const regions = [1 / 4, (1 / 4) * 2, (1 / 4) * 3]
      if (nT <= regions[0]) {
        rValue = 0
        gValue = 128 + 4 * nT * 127.999
        bValue = 0
      } else if (nT > regions[0] && nT <= regions[1]) {
        rValue = 768 - 4 * (1 - nT) * 255.999
        gValue = 255
        bValue = 0
      } else if (nT > regions[1] && nT <= regions[2]) {
        rValue = 255
        gValue = 768 - 4 * nT * 255.999
        bValue = 0
      } else {
        rValue = 128 + 4 * (1 - nT) * 127.999
        gValue = 0
        bValue = 0
      }
      break
    }

    default: {
      const regions = [1 / 4, (1 / 4) * 2, (1 / 4) * 3]
      if (nT <= regions[0]) {
        rValue = 0
        gValue = 4 * nT * 255.999
        bValue = 255
      } else if (nT > regions[0] && nT <= regions[1]) {
        rValue = 0
        gValue = 255
        bValue = 512 - 4 * nT * 255.999
      } else if (nT > regions[1] && nT <= regions[2]) {
        rValue = 512 - 4 * (1 - nT) * 255.999
        gValue = 255
        bValue = 0
      } else {
        rValue = 255
        gValue = 4 * (1 - nT) * 255.999
        bValue = 0
      }
      break
    }
  }

  return {
    r: checkRange(Math.trunc(rValue)),
    g: checkRange(Math.trunc(gValue)),
    b: checkRange(Math.trunc(bValue)),
  }
}
