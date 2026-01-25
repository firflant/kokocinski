/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./templates/**/*.html.twig",
    "./components/**/*.twig",
    "./src/**/*.{js,jsx,ts,tsx}",
    "../../modules/custom/**/*.html.twig",
  ],
  theme: {
    extend: {
      colors: {
        // Primary golden/amber color palette
        primary: {
          50: "#fdfaf5",
          100: "#faf5eb",
          200: "#f4e6cd",
          300: "#edd7af",
          400: "#e0ba73",
          500: "#d39d37",
          600: "#be8d32",
          700: "#9e752a",
          800: "#7e5d22",
          900: "#674c1c",
          950: "#4a3514",
        },
      },
      fontFamily: {
        // Add your custom fonts here
      },
    },
  },
  plugins: [require("@tailwindcss/forms"), require("@tailwindcss/typography")],
};
