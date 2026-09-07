/* =========================================================
   AGRIMART — FRONT-END LOGIC

   Authentication:
   PHP + MySQL

   Temporary demo storage:
   Cart, orders, and bookings still use localStorage for now.
   These can be converted to MySQL later.
   ========================================================= */


/* =========================================================
   HELPERS
   ========================================================= */

function pesos(amount) {
  const n = Number(amount || 0);

  return '\u20B1' + n.toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}


function qs(name) {
  return new URLSearchParams(
    window.location.search
  ).get(name);
}


function escapeHtml(str) {
  const div = document.createElement('div');

  div.textContent = str ?? '';

  return div.innerHTML;
}


/* =========================================================
   CART
   TEMPORARILY USING localStorage
   ========================================================= */

const Cart = {

  KEY: 'agrimart_cart',


  items() {

    return JSON.parse(
      localStorage.getItem(this.KEY) || '[]'
    );

  },


  save(items) {

    localStorage.setItem(
      this.KEY,
      JSON.stringify(items)
    );

    this.updateBadge();

  },


  count() {

    return this.items().reduce(
      (sum, item) => sum + item.quantity,
      0
    );

  },


  total() {

    return this.items().reduce(
      (sum, item) =>
        sum + item.price * item.quantity,
      0
    );

  },


  add(entry, qty) {

    qty = Math.max(
      1,
      Number(qty) || 1
    );

    const items = this.items();

    const existing = items.find(
      item => item.key === entry.key
    );

    if (existing) {

      existing.quantity += qty;

    } else {

      items.push({
        ...entry,
        quantity: qty
      });

    }

    this.save(items);

  },


  setQuantity(key, qty) {

    qty = Math.max(
      1,
      Number(qty) || 1
    );

    const items = this.items();

    const item = items.find(
      item => item.key === key
    );

    if (item) {

      item.quantity = qty;

    }

    this.save(items);

  },


  remove(key) {

    this.save(
      this.items().filter(
        item => item.key !== key
      )
    );

  },


  clear() {

    this.save([]);

  },


  updateBadge() {

    const badge =
      document.getElementById('cartCount');

    if (!badge) {
      return;
    }

    const count = this.count();

    badge.textContent = count;

    badge.dataset.empty =
      count === 0
        ? 'true'
        : 'false';

  }

};


/* =========================================================
   TEMPORARY DEMO STORAGE

   Authentication is NOT stored here anymore.

   Orders and bookings are temporarily stored here until
   they are connected to MySQL.
   ========================================================= */

const DemoStorage = {

  BOOKINGS_KEY: 'agrimart_bookings',

  ORDERS_KEY: 'agrimart_orders',


  addBooking(booking) {

    const list = JSON.parse(
      localStorage.getItem(
        this.BOOKINGS_KEY
      ) || '[]'
    );

    list.push(booking);

    localStorage.setItem(
      this.BOOKINGS_KEY,
      JSON.stringify(list)
    );

  },


  addOrder(order) {

    const list = JSON.parse(
      localStorage.getItem(
        this.ORDERS_KEY
      ) || '[]'
    );

    list.push(order);

    localStorage.setItem(
      this.ORDERS_KEY,
      JSON.stringify(list)
    );

  }

};


/* =========================================================
   HEADER
   Login status will be handled using PHP sessions.
   ========================================================= */

function initHeader() {

  const header =
    document.getElementById('siteHeader');


  if (header) {

    const onScroll = () => {

      header.classList.toggle(
        'is-solid',
        window.scrollY > 40
      );

    };


    window.addEventListener(
      'scroll',
      onScroll
    );


    onScroll();

  }


  Cart.updateBadge();

}


/* =========================================================
   PRODUCT CARD
   ========================================================= */

function productCard(product) {

  return `

    <a
      class="card"
      href="product-detail.html?id=${product.product_id}"
    >

      <div class="card-media">

        <img
          src="${product.image_url}"
          alt="${escapeHtml(product.product_name)}"
        >

        <span class="card-tag">
          ${escapeHtml(
            categoryName(product.category_id)
          )}
        </span>

      </div>


      <div class="card-body">

        <span class="card-cat">

          ${escapeHtml(
            product.farm_name ||
            product.seller_name
          )}

        </span>


        <h3>
          ${escapeHtml(product.product_name)}
        </h3>


        <p class="card-meta">

          ${product.quantity}

          ${escapeHtml(product.unit)}

          available

        </p>


        <div class="card-foot">

          <span class="price">

            ${pesos(product.price)}

            <small>
              / ${escapeHtml(product.unit)}
            </small>

          </span>


          <span class="btn btn-dark">
            View
          </span>

        </div>

      </div>

    </a>

  `;

}


/* =========================================================
   EQUIPMENT PRICE
   ========================================================= */

function equipmentPriceHtml(eq) {

  if (eq.listing_type === 'rent') {

    return `

      <span class="price">

        ${pesos(eq.rate_price)}

        <small>
          / ${escapeHtml(eq.rate_type)}
        </small>

      </span>

    `;

  }


  if (eq.listing_type === 'sale') {

    return `

      <span class="price">

        ${pesos(eq.sale_price)}

      </span>

    `;

  }


  return `

    <span class="price">

      ${pesos(eq.sale_price)}

      <small>

        or

        ${pesos(eq.rate_price)}

        /${escapeHtml(eq.rate_type)}

      </small>

    </span>

  `;

}


/* =========================================================
   EQUIPMENT CARD
   ========================================================= */

function equipmentCard(eq) {

  return `

    <a
      class="card"
      href="equipment-detail.html?id=${eq.equipment_id}"
    >

      <div class="card-media">

        <img
          src="${eq.image_url}"
          alt="${escapeHtml(eq.equipment_name)}"
        >

        <span class="card-tag">

          ${escapeHtml(
            categoryName(eq.category_id)
          )}

        </span>

      </div>


      <div class="card-body">

        <span class="card-cat">

          ${escapeHtml(eq.owner_name)}

        </span>


        <h3>

          ${escapeHtml(eq.equipment_name)}

        </h3>


        <p class="card-meta">

          ${escapeHtml(eq.brand)}

          ${escapeHtml(eq.model)}

          ·

          <span
            class="
              badge
              badge-${
                eq.availability === 'available'
                  ? 'available'
                  : 'rented'
              }
            "
          >

            ${escapeHtml(eq.availability)}

          </span>


          ${
            eq.listing_type === 'both'

              ? `
                <span class="badge badge-both">
                  buy or rent
                </span>
              `

              : ''
          }

        </p>


        <div class="card-foot">

          ${equipmentPriceHtml(eq)}

          <span class="btn btn-solid">
            View
          </span>

        </div>

      </div>

    </a>

  `;

}


/* =========================================================
   CATEGORY NAME
   ========================================================= */

function categoryName(id) {

  const category = CATEGORIES.find(
    category =>
      category.category_id === id
  );


  return category
    ? category.category_name
    : '';

}


/* =========================================================
   HOME PAGE
   ========================================================= */

function renderHome() {

  const catStrip =
    document.getElementById(
      'categoryStrip'
    );


  if (catStrip) {

    catStrip.innerHTML =
      CATEGORIES.map(
        (category, index) => `

          <a
            href="${
              category.category_type === 'seed'
                ? 'products.html'
                : 'equipment.html'
            }?category=${category.category_id}"
          >

            <span class="num">

              0${index + 1}

            </span>


            <span class="name">

              ${escapeHtml(
                category.category_name
              )}

            </span>


            <span class="type">

              ${escapeHtml(
                category.category_type
              )}

            </span>

          </a>

        `
      ).join('');

  }


  const featuredProducts =
    document.getElementById(
      'featuredProducts'
    );


  if (featuredProducts) {

    featuredProducts.innerHTML =
      PRODUCTS
        .slice(0, 3)
        .map(productCard)
        .join('');

  }


  const featuredEquipment =
    document.getElementById(
      'featuredEquipment'
    );


  if (featuredEquipment) {

    featuredEquipment.innerHTML =
      EQUIPMENT
        .slice(0, 3)
        .map(equipmentCard)
        .join('');

  }

}


/* =========================================================
   PRODUCTS PAGE
   ========================================================= */

function renderProducts() {

  const grid =
    document.getElementById(
      'productGrid'
    );


  if (!grid) {
    return;
  }


  const categorySelect =
    document.getElementById(
      'categorySelect'
    );


  const seedCategories =
    CATEGORIES.filter(
      category =>
        category.category_type === 'seed'
    );


  if (categorySelect) {

    categorySelect.innerHTML =

      '<option value="">All categories</option>' +

      seedCategories.map(
        category => `

          <option value="${category.category_id}">

            ${escapeHtml(
              category.category_name
            )}

          </option>

        `
      ).join('');

  }


  function apply() {

    const search =
      (
        document
          .getElementById('searchInput')
          ?.value || ''
      )
      .toLowerCase()
      .trim();


    const category =
      categorySelect?.value || '';


    const results =
      PRODUCTS.filter(
        product => {

          const matchesSearch =

            !search ||

            product
              .product_name
              .toLowerCase()
              .includes(search)

            ||

            product
              .description
              .toLowerCase()
              .includes(search);


          const matchesCategory =

            !category ||

            String(
              product.category_id
            ) === category;


          return (
            matchesSearch &&
            matchesCategory
          );

        }
      );


    grid.innerHTML =
      results.length

        ? results
            .map(productCard)
            .join('')

        : `

          <div class="empty-state">

            No products match that search.

            Try a different keyword
            or category.

          </div>

        `;

  }


  const initialCategory =
    qs('category');


  if (
    initialCategory &&
    categorySelect
  ) {

    categorySelect.value =
      initialCategory;

  }


  document
    .getElementById('filterForm')
    ?.addEventListener(
      'submit',
      event => {

        event.preventDefault();

        apply();

      }
    );


  document
    .getElementById('clearFilters')
    ?.addEventListener(
      'click',
      event => {

        event.preventDefault();


        const searchInput =
          document.getElementById(
            'searchInput'
          );


        if (searchInput) {

          searchInput.value = '';

        }


        if (categorySelect) {

          categorySelect.value = '';

        }


        apply();

      }
    );


  apply();

}


/* =========================================================
   PRODUCT DETAIL
   ========================================================= */

function renderProductDetail() {

  const root =
    document.getElementById(
      'productDetail'
    );


  if (!root) {
    return;
  }


  const id =
    Number(qs('id'));


  const product =
    PRODUCTS.find(
      product =>
        product.product_id === id
    );


  if (!product) {

    root.innerHTML = `

      <div class="empty-state">

        Product not found.

        <a href="products.html">

          Back to Products

        </a>

      </div>

    `;

    return;

  }


  document.title =
    product.product_name +
    ' — AgriMart';


  root.innerHTML = `

    <div class="detail">

      <div class="detail-media">

        <img
          src="${product.image_url}"
          alt="${escapeHtml(product.product_name)}"
        >

      </div>


      <div class="detail-info">

        <span
          class="eyebrow"
          style="color:var(--moss-500)"
        >

          ${escapeHtml(
            categoryName(
              product.category_id
            )
          )}

        </span>


        <h1>

          ${escapeHtml(
            product.product_name
          )}

        </h1>


        <div class="detail-price">

          ${pesos(product.price)}

          <small>

            / ${escapeHtml(product.unit)}

          </small>

        </div>


        <p class="detail-desc">

          ${escapeHtml(
            product.description
          )}

        </p>


        <dl class="spec-list">

          <div>

            <dt>
              Available Quantity
            </dt>

            <dd>

              ${product.quantity}

              ${escapeHtml(product.unit)}

            </dd>

          </div>


          <div>

            <dt>Status</dt>

            <dd style="text-transform:capitalize">

              ${escapeHtml(product.status)}

            </dd>

          </div>


          <div>

            <dt>Farm</dt>

            <dd>

              ${escapeHtml(
                product.farm_name || '—'
              )}

            </dd>

          </div>


          <div>

            <dt>Location</dt>

            <dd>

              ${escapeHtml(
                product.farm_location || '—'
              )}

            </dd>

          </div>

        </dl>


        <div id="formMsg"></div>


        <div id="productActions"></div>


        <div class="seller-box">

          <span
            class="eyebrow"
            style="color:var(--moss-500)"
          >

            Listed by

          </span>


          <h3 style="font-size:17px">

            ${escapeHtml(
              product.seller_name
            )}

          </h3>

        </div>

      </div>

    </div>

  `;


  const actions =
    document.getElementById(
      'productActions'
    );


  /*
   * PHP will later control whether
   * the user is allowed to order.
   */

  actions.innerHTML = `

    <form
      id="productOrderForm"
      style="
        margin-top:10px;
        display:grid;
        gap:16px;
      "
    >

      <div
        class="field"
        style="max-width:140px"
      >

        <label for="productQty">

          Quantity
          (${escapeHtml(product.unit)})

        </label>


        <input
          type="number"
          id="productQty"
          value="1"
          min="1"
          max="${product.quantity}"
          required
        >

      </div>


      <div
        class="detail-actions"
        style="margin-top:0"
      >

        <button
          type="submit"
          class="btn btn-dark"
          id="addToCartBtn"
        >

          Add to Cart

        </button>


        <button
          type="submit"
          class="btn btn-solid"
          id="buyNowBtn"
        >

          Buy Now

        </button>

      </div>

    </form>

  `;


  const cartEntry = () => ({

    key:
      'product-' +
      product.product_id,

    type:
      'product',

    id:
      product.product_id,

    name:
      product.product_name,

    price:
      product.price,

    unit:
      product.unit,

    image:
      product.image_url

  });


  document
    .getElementById(
      'addToCartBtn'
    )
    ?.addEventListener(
      'click',
      event => {

        event.preventDefault();


        const qty =
          Number(
            document
              .getElementById(
                'productQty'
              )
              .value
          ) || 1;


        Cart.add(
          cartEntry(),
          qty
        );


        showFormMessage(

          `Added ${qty} ${product.unit} of ${product.product_name} to your cart.`,

          'success'

        );

      }
    );


  document
    .getElementById(
      'buyNowBtn'
    )
    ?.addEventListener(
      'click',
      event => {

        event.preventDefault();


        const qty =
          Number(
            document
              .getElementById(
                'productQty'
              )
              .value
          ) || 1;


        Cart.add(
          cartEntry(),
          qty
        );


        window.location.href =
          'cart.html';

      }
    );

}


/* =========================================================
   EQUIPMENT PAGE
   ========================================================= */

function renderEquipment() {

  const grid =
    document.getElementById(
      'equipmentGrid'
    );


  if (!grid) {
    return;
  }


  const categorySelect =
    document.getElementById(
      'categorySelect'
    );


  const equipmentCategories =
    CATEGORIES.filter(
      category =>
        category.category_type ===
        'equipment'
    );


  if (categorySelect) {

    categorySelect.innerHTML =

      '<option value="">All categories</option>' +

      equipmentCategories.map(
        category => `

          <option value="${category.category_id}">

            ${escapeHtml(
              category.category_name
            )}

          </option>

        `
      ).join('');

  }


  let currentType =
    qs('type') || '';


  if (
    !['rent', 'sale']
      .includes(currentType)
  ) {

    currentType = '';

  }


  function setTabs() {

    document
      .querySelectorAll(
        '.listing-tabs a'
      )
      .forEach(
        link => {

          link.classList.toggle(

            'active',

            link.dataset.type ===
              currentType

          );

        }
      );

  }


  function apply() {

    const search =
      (
        document
          .getElementById(
            'searchInput'
          )
          ?.value || ''
      )
      .toLowerCase()
      .trim();


    const category =
      categorySelect?.value || '';


    const results =
      EQUIPMENT.filter(
        eq => {

          const matchesSearch =

            !search ||

            eq
              .equipment_name
              .toLowerCase()
              .includes(search)

            ||

            eq
              .description
              .toLowerCase()
              .includes(search);


          const matchesCategory =

            !category ||

            String(
              eq.category_id
            ) === category;


          const matchesType =

            !currentType ||

            eq.listing_type ===
              currentType

            ||

            eq.listing_type ===
              'both';


          return (

            matchesSearch &&

            matchesCategory &&

            matchesType

          );

        }
      );


    grid.innerHTML =
      results.length

        ? results
            .map(equipmentCard)
            .join('')

        : `

          <div class="empty-state">

            No equipment matches
            that search.

          </div>

        `;

  }


  const initialCategory =
    qs('category');


  if (
    initialCategory &&
    categorySelect
  ) {

    categorySelect.value =
      initialCategory;

  }


  document
    .querySelectorAll(
      '.listing-tabs a'
    )
    .forEach(
      link => {

        link.addEventListener(
          'click',
          event => {

            event.preventDefault();


            currentType =
              link.dataset.type;


            setTabs();

            apply();

          }
        );

      }
    );


  document
    .getElementById(
      'filterForm'
    )
    ?.addEventListener(
      'submit',
      event => {

        event.preventDefault();

        apply();

      }
    );


  document
    .getElementById(
      'clearFilters'
    )
    ?.addEventListener(
      'click',
      event => {

        event.preventDefault();


        const search =
          document.getElementById(
            'searchInput'
          );


        if (search) {
          search.value = '';
        }


        if (categorySelect) {
          categorySelect.value = '';
        }


        apply();

      }
    );


  setTabs();

  apply();

}


/* =========================================================
   EQUIPMENT DETAIL
   ========================================================= */

function renderEquipmentDetail() {

  const root =
    document.getElementById(
      'equipmentDetail'
    );


  if (!root) {
    return;
  }


  const id =
    Number(qs('id'));


  const eq =
    EQUIPMENT.find(
      equipment =>
        equipment.equipment_id === id
    );


  if (!eq) {

    root.innerHTML = `

      <div class="empty-state">

        Equipment not found.

        <a href="equipment.html">

          Back to Equipment

        </a>

      </div>

    `;

    return;

  }


  document.title =
    eq.equipment_name +
    ' — AgriMart';


  const canRent =

    eq.listing_type === 'rent'

    ||

    eq.listing_type === 'both';


  const canBuy =

    eq.listing_type === 'sale'

    ||

    eq.listing_type === 'both';


  let action =
    qs('action')

    ||

    (
      canBuy && !canRent
        ? 'buy'
        : 'rent'
    );


  if (
    action === 'buy' &&
    !canBuy
  ) {

    action = 'rent';

  }


  if (
    action === 'rent' &&
    !canRent
  ) {

    action = 'buy';

  }


  const priceHtml =

    eq.listing_type === 'both'

      ? `

        ${pesos(eq.sale_price)}

        <small>to buy</small>

        &nbsp;·&nbsp;

        ${pesos(eq.rate_price)}

        <small>

          / ${escapeHtml(eq.rate_type)}
          to rent

        </small>

      `

      :

      eq.listing_type === 'sale'

        ? `${pesos(eq.sale_price)}`

        : `

          ${pesos(eq.rate_price)}

          <small>

            / ${escapeHtml(eq.rate_type)}

          </small>

        `;


  root.innerHTML = `

    <div class="detail">

      <div class="detail-media">

        <img
          src="${eq.image_url}"
          alt="${escapeHtml(eq.equipment_name)}"
        >

      </div>


      <div class="detail-info">

        <span
          class="eyebrow"
          style="color:var(--moss-500)"
        >

          ${escapeHtml(
            categoryName(eq.category_id)
          )}

        </span>


        <h1>

          ${escapeHtml(
            eq.equipment_name
          )}

        </h1>


        <div class="detail-price">

          ${priceHtml}

        </div>


        <p class="detail-desc">

          ${escapeHtml(eq.description)}

        </p>


        <dl class="spec-list">

          <div>

            <dt>Brand</dt>

            <dd>
              ${escapeHtml(eq.brand || '—')}
            </dd>

          </div>


          <div>

            <dt>Model</dt>

            <dd>
              ${escapeHtml(eq.model || '—')}
            </dd>

          </div>


          <div>

            <dt>Availability</dt>

            <dd style="text-transform:capitalize">

              ${escapeHtml(
                eq.availability
              )}

            </dd>

          </div>


          <div>

            <dt>Owner</dt>

            <dd>
              ${escapeHtml(eq.owner_name)}
            </dd>

          </div>

        </dl>


        <div id="formMsg"></div>


        <div id="equipmentActions"></div>


        <div class="seller-box">

          <span
            class="eyebrow"
            style="color:var(--moss-500)"
          >

            Owned by

          </span>


          <h3 style="font-size:17px">

            ${escapeHtml(eq.owner_name)}

          </h3>

        </div>

      </div>

    </div>

  `;


  const actionsEl =
    document.getElementById(
      'equipmentActions'
    );


  if (
    eq.availability !==
    'available'
  ) {

    actionsEl.innerHTML = `

      <div class="alert alert-error">

        This equipment is currently

        ${escapeHtml(eq.availability)}

        and is not open for booking
        or purchase.

      </div>

    `;

    return;

  }


  /*
   * Old buyer/farmer/equipment_owner
   * role checking was removed.
   *
   * PHP will later protect these
   * actions using the logged-in user.
   */


  let tabsHtml = '';


  if (
    canBuy &&
    canRent
  ) {

    tabsHtml = `

      <div
        class="listing-tabs"
        style="margin-top:26px"
      >

        <a
          href="?id=${eq.equipment_id}&action=buy"
          class="${
            action === 'buy'
              ? 'active'
              : ''
          }"
        >

          Buy Equipment

        </a>


        <a
          href="?id=${eq.equipment_id}&action=rent"
          class="${
            action === 'rent'
              ? 'active'
              : ''
          }"
        >

          Rent Equipment

        </a>

      </div>

    `;

  }


  let formHtml = '';


  if (
    action === 'buy' &&
    canBuy
  ) {

    formHtml = `

      <form
        id="buyForm"
        style="
          margin-top:20px;
          display:grid;
          gap:16px;
        "
      >

        <div class="field">

          <label for="quantity">
            Quantity
          </label>


          <input
            type="number"
            id="quantity"
            value="1"
            min="1"
            required
          >

        </div>


        <div class="field">

          <label for="deliveryAddress">

            Delivery Address

          </label>


          <input
            type="text"
            id="deliveryAddress"
            placeholder="Where should this be delivered?"
            required
          >

        </div>


        <div
          class="detail-actions"
          style="margin-top:0"
        >

          <button
            type="submit"
            class="btn btn-dark"
            id="addToCartBtn"
          >

            Add to Cart

          </button>


          <button
            type="submit"
            class="btn btn-solid"
            id="buyNowBtn"
          >

            Buy Now

          </button>

        </div>

      </form>

    `;

  }


  else if (
    action === 'rent' &&
    canRent
  ) {

    formHtml = `

      <form
        id="rentForm"
        style="
          margin-top:20px;
          display:grid;
          gap:16px;
        "
      >

        <div class="field">

          <label for="startDate">

            Start Date

          </label>


          <input
            type="date"
            id="startDate"
            required
          >

        </div>


        <div class="field">

          <label for="endDate">

            End Date

          </label>


          <input
            type="date"
            id="endDate"
            required
          >

        </div>


        <div class="field">

          <label for="pickupLocation">

            Pickup Location

          </label>


          <input
            type="text"
            id="pickupLocation"
            placeholder="e.g. Barangay Poblacion"
            required
          >

        </div>


        <div class="field">

          <label for="dropoffLocation">

            Dropoff Location

          </label>


          <input
            type="text"
            id="dropoffLocation"
            placeholder="Where to return the equipment"
            required
          >

        </div>


        <button
          type="submit"
          class="btn btn-solid btn-block"
        >

          Request Booking

        </button>

      </form>

    `;

  }


  actionsEl.innerHTML =
    tabsHtml +
    formHtml;


  document
    .getElementById(
      'addToCartBtn'
    )
    ?.addEventListener(
      'click',
      event => {

        event.preventDefault();


        const quantity =
          Number(
            document
              .getElementById(
                'quantity'
              )
              .value
          ) || 1;


        Cart.add(
          {

            key:
              'equipment-' +
              eq.equipment_id,

            type:
              'equipment',

            id:
              eq.equipment_id,

            name:
              eq.equipment_name,

            price:
              eq.sale_price,

            unit:
              'unit',

            image:
              eq.image_url

          },

          quantity

        );


        showFormMessage(

          `Added ${quantity} × ${eq.equipment_name} to your cart.`,

          'success'

        );

      }
    );


  document
    .getElementById(
      'buyNowBtn'
    )
    ?.addEventListener(
      'click',
      event => {

        event.preventDefault();


        const quantity =
          Number(
            document
              .getElementById(
                'quantity'
              )
              .value
          ) || 1;


        const deliveryAddress =
          document
            .getElementById(
              'deliveryAddress'
            )
            .value
            .trim();


        if (!deliveryAddress) {

          showFormMessage(

            'Please provide a delivery address.',

            'error'

          );

          return;

        }


        DemoStorage.addOrder({

          equipment_id:
            eq.equipment_id,

          equipment_name:
            eq.equipment_name,

          quantity:
            quantity,

          delivery_address:
            deliveryAddress,

          total_amount:
            eq.sale_price *
            quantity,

          status:
            'pending',

          created_at:
            new Date()
              .toISOString()

        });


        showFormMessage(

          'Order placed! The owner will confirm your purchase shortly.',

          'success'

        );


        document
          .getElementById(
            'buyForm'
          )
          ?.reset();

      }
    );


  document
    .getElementById(
      'rentForm'
    )
    ?.addEventListener(
      'submit',
      event => {

        event.preventDefault();


        const startDate =
          document
            .getElementById(
              'startDate'
            )
            .value;


        const endDate =
          document
            .getElementById(
              'endDate'
            )
            .value;


        const pickupLocation =
          document
            .getElementById(
              'pickupLocation'
            )
            .value
            .trim();


        const dropoffLocation =
          document
            .getElementById(
              'dropoffLocation'
            )
            .value
            .trim();


        if (
          !startDate ||
          !endDate ||
          !pickupLocation ||
          !dropoffLocation
        ) {

          return;

        }


        if (
          new Date(endDate) <
          new Date(startDate)
        ) {

          showFormMessage(

            'End date must be on or after the start date.',

            'error'

          );

          return;

        }


        const days =
          Math.max(

            1,

            (
              new Date(endDate) -
              new Date(startDate)
            )

            / 86400000

          );


        const multiplier =

          eq.rate_type === 'hourly'

            ? days * 8

            : days;


        DemoStorage.addBooking({

          equipment_id:
            eq.equipment_id,

          equipment_name:
            eq.equipment_name,

          start_date:
            startDate,

          end_date:
            endDate,

          pickup_location:
            pickupLocation,

          dropoff_location:
            dropoffLocation,

          total_amount:
            Math.round(
              eq.rate_price *
              multiplier *
              100
            ) / 100,

          status:
            'pending',

          created_at:
            new Date()
              .toISOString()

        });


        showFormMessage(

          'Booking request sent! The owner will confirm your reservation shortly.',

          'success'

        );


        event.target.reset();

      }
    );

}


/* =========================================================
   FORM MESSAGE
   ========================================================= */

function showFormMessage(
  message,
  type
) {

  const element =
    document.getElementById(
      'formMsg'
    );


  if (!element) {
    return;
  }


  element.innerHTML = `

    <div
      class="alert alert-${type}"
    >

      ${escapeHtml(message)}

    </div>

  `;

}


/* =========================================================
   CART PAGE
   ========================================================= */

function renderCart() {

  const root =
    document.getElementById(
      'cartRoot'
    );


  if (!root) {
    return;
  }


  function paint() {

    const items =
      Cart.items();


    if (!items.length) {

      root.innerHTML = `

        <div class="cart-empty">

          <span
            class="eyebrow"
            style="color:var(--moss-500)"
          >

            Your cart

          </span>


          <h2 style="margin-top:10px">

            Nothing here yet

          </h2>


          <p>

            Browse products or
            equipment and add
            something to your cart.

          </p>


          <div
            class="detail-actions"
            style="justify-content:center"
          >

            <a
              href="products.html"
              class="btn btn-dark"
            >

              Browse Products

            </a>


            <a
              href="equipment.html"
              class="btn btn-solid"
            >

              Browse Equipment

            </a>

          </div>

        </div>

      `;

      return;

    }


    const rows =
      items.map(
        item => `

          <div
            class="cart-row"
            data-key="${item.key}"
          >

            <img
              src="${item.image}"
              alt="${escapeHtml(item.name)}"
            >


            <div>

              <h4>

                ${escapeHtml(item.name)}

              </h4>


              <span class="cart-meta">

                ${escapeHtml(item.type)}

                ·

                ${pesos(item.price)}

                /

                ${escapeHtml(item.unit)}

              </span>

              <br>


              <a
                href="#"
                class="cart-remove"
                data-remove="${item.key}"
              >

                Remove

              </a>

            </div>


            <div class="qty-stepper">

              <button
                type="button"
                data-step="-1"
                data-key="${item.key}"
              >

                −

              </button>


              <input
                type="number"
                min="1"
                value="${item.quantity}"
                data-qty="${item.key}"
              >


              <button
                type="button"
                data-step="1"
                data-key="${item.key}"
              >

                +

              </button>

            </div>


            <span class="cart-price">

              ${pesos(
                item.price *
                item.quantity
              )}

            </span>

          </div>

        `
      ).join('');


    const total =
      Cart.total();


    root.innerHTML = `

      <div class="section-head">

        <div>

          <span
            class="eyebrow"
            style="color:var(--moss-500)"
          >

            Your cart

          </span>


          <h2>

            ${items.length}

            item${
              items.length > 1
                ? 's'
                : ''
            }

            ready for checkout

          </h2>

        </div>

      </div>


      <div class="cart-layout">

        <div class="cart-items">

          ${rows}

        </div>


        <aside class="cart-summary">

          <h3>

            Order Summary

          </h3>


          <div class="row">

            <span>
              Subtotal
            </span>

            <span>
              ${pesos(total)}
            </span>

          </div>


          <div class="row">

            <span>
              Delivery
            </span>

            <span>

              Arranged with seller

            </span>

          </div>


          <div class="row total">

            <span>Total</span>

            <span>
              ${pesos(total)}
            </span>

          </div>


          <button
            class="btn btn-solid btn-block"
            id="checkoutBtn"
            style="margin-top:22px"
          >

            Checkout

          </button>


          <div id="checkoutMsg"></div>

        </aside>

      </div>

    `;


    root
      .querySelectorAll(
        '[data-remove]'
      )
      .forEach(
        link => {

          link.addEventListener(
            'click',
            event => {

              event.preventDefault();


              Cart.remove(
                link.dataset.remove
              );


              paint();

            }
          );

        }
      );


    root
      .querySelectorAll(
        '[data-step]'
      )
      .forEach(
        button => {

          button.addEventListener(
            'click',
            () => {

              const key =
                button.dataset.key;


              const item =
                Cart
                  .items()
                  .find(
                    item =>
                      item.key === key
                  );


              if (!item) {
                return;
              }


              const next =
                Math.max(

                  1,

                  item.quantity +

                  Number(
                    button.dataset.step
                  )

                );


              Cart.setQuantity(
                key,
                next
              );


              paint();

            }
          );

        }
      );


    root
      .querySelectorAll(
        '[data-qty]'
      )
      .forEach(
        input => {

          input.addEventListener(
            'change',
            () => {

              Cart.setQuantity(

                input.dataset.qty,

                input.value

              );


              paint();

            }
          );

        }
      );


    document
      .getElementById(
        'checkoutBtn'
      )
      ?.addEventListener(
        'click',
        () => {

          const message =
            document.getElementById(
              'checkoutMsg'
            );


          if (message) {

            message.innerHTML = `

              <div
                class="alert alert-success"
                style="margin-top:16px"
              >

                Order placed!

                Checkout is temporarily
                running in demo mode.

              </div>

            `;

          }


          Cart.clear();


          setTimeout(
            paint,
            1600
          );

        }
      );

  }


  paint();

}


/* =========================================================
   LOGIN / REGISTER

   IMPORTANT:

   There is NO JavaScript login or registration here anymore.

   login.php
       ↓
   login_process.php
       ↓
   MySQL

   register.php
       ↓
   register_process.php
       ↓
   MySQL

   Do NOT add e.preventDefault() to the login/register forms.
   ========================================================= */


/* =========================================================
   TOAST
   ========================================================= */

function toast(message) {

  let element =
    document.getElementById(
      'agrimartToast'
    );


  if (!element) {

    element =
      document.createElement(
        'div'
      );


    element.id =
      'agrimartToast';


    element.style.cssText =

      'position:fixed;' +

      'bottom:24px;' +

      'left:50%;' +

      'transform:translateX(-50%);' +

      'background:var(--forest-950);' +

      'color:var(--cream-50);' +

      'padding:14px 22px;' +

      'border-radius:4px;' +

      'font-family:var(--font-mono);' +

      'font-size:13px;' +

      'z-index:999;' +

      'box-shadow:0 12px 30px rgba(0,0,0,.3);';


    document.body.appendChild(
      element
    );

  }


  element.textContent =
    message;


  element.style.display =
    'block';


  clearTimeout(
    element._t
  );


  element._t =
    setTimeout(
      () => {

        element.style.display =
          'none';

      },

      3200
    );

}


/* =========================================================
   BOOT
   ========================================================= */

document.addEventListener(
  'DOMContentLoaded',
  () => {

    initHeader();

    renderHome();

    renderProducts();

    renderProductDetail();

    renderEquipment();

    renderEquipmentDetail();

    renderCart();

  }
);