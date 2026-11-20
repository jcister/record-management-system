# Run npm install
npm i
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }


# Install composer dependencies
composer install
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

# Run build
npm run build
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

# Run dev
composer run dev:win
