<nav class="navbar navbar-default navbar-static-top">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#app-navbar-collapse">
                <span class="sr-only">Toggle Navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="{{ url('/') }}">
                Hsing-Ying Shop
            </a>
        </div>
        <div class="collapse navbar-collapse" id="app-navbar-collapse">
            <ul class="nav navbar-nav">
                <!-- 顶部类目菜单开始 -->
                <!-- 判断模板是否有 $categoryTree 变量 -->
                @if(isset($categoryTree))
                    <li>
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown">所有分類 <b class="caret"></b></a>
                        <ul class="dropdown-menu multi-level">
                            <!-- 遍历 $categoryTree 集合，将集合中的每一项以 $category 变量注入 layouts._category_item 模板中并渲染 -->
                            @each('layouts._category_item', $categoryTree, 'category')
                        </ul>
                    </li>
            @endif
            <!-- 顶部类目菜单结束 -->
            </ul>
            <ul class="nav navbar-nav navbar-right">
                @guest
                    <li><a href="{{route('login')}}">登入</a></li>
                    <li><a href="{{route('register')}}">註冊</a></li>
                @else
                    <li>
                        <a href="{{ route('cart.index') }}"><span class="glyphicon glyphicon-shopping-cart" aria-hidden="true"></span></a>
                    </li>
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">
                            <span class="user-avatar pull-left" style="margin-right:8px; margin-top:-5px;">
                                <img src="https://iocaffcdn.phphub.org/uploads/images/201709/20/1/PtDKbASVcz.png?imageView2/1/w/60/h/60" class="img-responsive img-circle" width="30px" height="30px">
                            </span>
                            {{ Auth::user()->name }} <span class="caret"></span>
                        </a>
                        <ul class="dropdown-menu" role="menu">
                            <li>
                                <a href="{{  route('user_addresses.index') }}">收貨地址</a>
                            </li>
                            <li>
                                <a href="{{  route('orders.index') }}">我的訂單</a>
                            </li>
                            <li>
                                <a href="{{  route('installments.index') }}">分期付款</a>
                            </li>
                            <li>
                                <a href="{{ route('products.favorites') }}">我的收藏</a>
                            </li>
                            <li>
                                <a href="{{ route('logout') }}"
                                   onclick="event.preventDefault();
                                             document.getElementById('logout-form').submit();">
                                    登出
                                </a>
                                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                    {{ csrf_field() }}
                                </form>
                            </li>
                        </ul>
                    </li>
                @endguest
            </ul>
        </div>
    </div>
</nav>