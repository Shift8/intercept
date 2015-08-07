# Intercept PHP

Provides base classes to extend from that allow you to easily build classes with filterable methods (intercepting filter pattern).
It's a small (about 1,100 lines of code across 3 files including the many comments) library that's easy to work with and gives your 
library a ton of flexibility.

This is not a framework or even a micro-framework. It's simply a starting point. You could go and build a framework from it, 
but I suggest you look at Lithium (li3) at that point because that's where a lot of this comes from.

Using these base clasess will lead to less code maintenance for users of your library (and yourself) as well as less breaking 
changes as your API changes. It also helps with the separatation of concerns.

## Goals

Real simple, there's two main goals for this library (or boilerplate set of classes).

1. Provide a configuration convention
2. Allow methods to be filterable

### Configuration Convention

You'll notice the constructors of both the ```StaticObject``` and ```Object``` class take an array of options.
This convention removes the problem with positional parameters and, if you adopt it, leaves you with less breaking changes 
and code maintenance in the future. Some intelligent people call this a unified constructor.

You of course are completely free to deviate away from this. You don't need to buy into it. Maybe you want to bring
in a library for dependency injection...By all means, have at it.

However, for simple libraries a whole DI system may be too heavy handed. So this simple configuration convention provides
a nice guide for you. It is subtle, yet important.

Remember, positional arguments are the path to the dark side. Positional arguments in constructors leads to API changes. 
API changes leads to breaking changes. Breaking changes leads to suffering.

### Filterable Methods

**TL;DR ?**    
The short of it is using this approach, over others, will save you and the users of your library a lot of time and headaches.

**The Issue at Hand & Some Other Solutions**

If you want to write a PHP classes that other people use (open-source or otherwise) then you need to be aware that someone 
is going to either disagree with the way you did something or just want to do something differently. This could be a very 
small tweak even.

However, this is where the ball of yarn starts to unravel. Here is where we get into maintence hell. Here is where versioning 
becomes important and breaking changes can be so easily introduced to a project.

There are several ways in which some classes and methods allow end-users to work with them in a more flexible manner.

**1. Extend the class**

Perhaps the simpliest way is go extend it. If you want to change something, this is object-oriented programming...Have at it. 
Sure, that works...But it's the easiest way to run into breaking changes when the original author updates their library and 
the end user wants to obtain those updates. 

It's also a lot of extra work. You now need to create a new class and extend some other class, possibly bring in other classes
from that library as well depending on the complexity just for perhaps a small adjustment.

If you're going to extend a class, extend the ones here.

**2. Hooks**

These are commonly found in many PHP frameworks and libraries. If you're familiar with CakePHP, for example, you'll surely 
know about ```beforeFilter()``` and ```afterFilter()```. The problem with these kind of hooks is that they are called at 
fixed points in the codebase. You don't get a lot of flexibility.

Users of Drupal probably closely identify with the "hook" term as they are all over that CMS an are a little different than
what you'll find in CakePHP. In fact, if you simply take a list of all the available hooks in Drupal you'll quickly start 
to realize why this can be a complete maintenance nightmare.

**3. Event Dispatchers**

Events are very similar to hooks only instead there can be more. It's not quite as limited, but when those events are dispatched
still is limited. Of course you also then need to write functions to handle those events. So there's even more code to be written 
and maintained.

The Symfony framework has an event dispatcher system. If you take a look at the documentation for that, you'll see just how much
extra work is involved there.

**Separation of Concerns**

It's important to keep this in mind. All of the above methods, except perhaps event dispatchers, leave you a bit stuck with 
where you need to write code. So if you need to perform other tasks that are best kept separate from an organizational or 
architectural point of view (like, say, logging), you kinda can't. 

With the intercepting filter pattern, you can. The original methods won't know a thing about your logging class or method calls. 
Those calls won't even need to be made from that method or even in the same PHP file for that matter.

This helps with maintenance, testing, architecture, organization, and working with teams! Yes, you can more easily work with 
others if you aren't constantly fighting over the same files. Even though we have revision control and branches and merging, 
it's still nice to work in an organized and isolated fashion (I'm not saying don't collaborate or talk to each other, I'm just 
saying you will step on each other's toes from time to time and this alleviates that).

**A Better Way: The Intercepting Filter**

The alternative to the above is the filter system provided in these core classes. These two classes are the only ones you'll 
need to extend for your own needs. When you write methods you can now write them in such a way that they are filterable.

This allows someone else to apply any number of closures that run in a chain. These pass all of the parameters around and 
they are able to change the behavior of the method. Not just do something after it's done or before it happens.

It's a much more flexible solution and provides you with greater control.

It also separates concerns because end users are able to apply these filters from anywhere in their codebase, so long as it's
before the method gets called of course.

This library provides methods that allow you to work with this chain in a fairly robust way. You can tag each filter with 
a name. You can apply filters in any order you wish. You can remove filters or replace them.

So if you write some code that uses a library that uses this library and then someone else writes some code using your library
and then someone else uses that code...Everyone along the way can be modifying the behavior of your original code!

Confused? Yea! It's a bit hard to explain, but the idea here is less forking and less phrases like "hack the core" floating around.
You'll just have to see it in practice and don't worry, we have examples.

## Usage Examples

Ok, so this magic pattern sounds good by now hopefully. It's different, it uses closures in a tricky way to get it done and you 
probably want to know how it works.

Perhaps the best way to learn is by taking a look at the examples included in this library. Any class you create that extends
```Object``` or ```StaticObject``` will allow you to make filterable methods. The magic part is:

```
return $this->_filter(__METHOD__, $params, function($self, $params) {
	// Whatever you want to do, do it here and return the result.
	return 'test result';
});
```

Basically you're returning a closure and within it is where you'll want to put all of your code. ```$params``` is going to be 
an array of parameters you wish to keep passing through the chain. They are taken from the parameters your method takes in.
You can simply use PHP's ```compact()``` function to help you out here. Then within the closure you're free to go the other 
way and use ```extract()``` or simply address the paremeters in the keyed array.

This is basically the only thing you need to remember when writing your own methods.

It works pretty much the same way with static methods:

```
$params = compact('options');
return static::_filter(__FUNCTION__, $params, function($self, $params) {
	// Whatever you want to do, do it here and return the result.
	return 'test static result';
});
```

Then you, and anyone using your clases, can filter the methods you've exposed to be filtered.

TBD

## Drawbacks

Yes, there are some drawbacks. Always in programming and here is no exception. The biggest thing you'll notice is that debugging 
your code is a little bit harder. You're now inside these closures. Code completion can be a little bit difficult here too.

The rule of thumb here is if the modifications to the methods are to getting to be too complex (and judgement must be used here), 
then maybe the end user is better off forking the library or extending classes within it. This library can't fit every scenario 
of course, but it's certainly a good place to start.

The other drawback (and this is also true for an event handler approach) is that since you have a bit more flexibility, you need 
to be organized in where you put your code. You can apply filteres from practically anywhere, but that doesn't mean you should 
be applying them from everywhere. Keep your code organized. This is a responsibility of both the library author and the end user.
